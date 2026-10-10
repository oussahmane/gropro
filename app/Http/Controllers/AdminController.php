<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard()
    {
        $orders = Order::with('worker')->orderBy('created_at', 'desc')->get();

        return view('admin.dashboard', compact('orders'));
    }

    public function ordersPartial()
    {
        $orders = Order::with('worker')->orderBy('created_at', 'desc')->get();

        return view('admin.partials.orders_table', compact('orders'));
    }

    public function showOrder(Order $order)
    {
        $order->load(['items', 'worker']);

        return view('admin.orders.show', compact('order'));
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => ['required', Rule::in(['draft', 'received', 'processing', 'completed', 'cancelled'])],
        ]);

        $order->update(['status' => $request->status]);

        return back()->with('success', 'Order status updated.');
    }

    public function destroyOrder(Order $order)
    {
        $order->delete();

        return redirect()->route('admin.dashboard')->with('success', 'Order removed successfully.');
    }

    public function catalog()
    {
        return view('admin.catalog.index');
    }

    public function previewCatalog(Request $request)
    {
        $request->validate([
            'catalog_file' => 'required|file|max:10240', // 10MB max
        ]);

        $file = $request->file('catalog_file');

        try {
            $fileContent = file_get_contents($file->getPathname());
            // Normalize line endings
            $fileContent = str_replace(["\r\n", "\r"], "\n", $fileContent);
            $lines = explode("\n", $fileContent);

            $validRows = [];
            $invalidRows = [];

            $isFirstRow = true;
            $designationCol = -1;
            $qtuCol = -1;

            // Auto detect delimiter
            $delimiter = "\t"; // default to tab
            if (isset($lines[0]) && strpos($lines[0], "\t") === false && strpos($lines[0], ';') !== false) {
                $delimiter = ';';
            } elseif (isset($lines[0]) && strpos($lines[0], "\t") === false && strpos($lines[0], ',') !== false) {
                $delimiter = ',';
            }

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $rowData = str_getcsv($line, $delimiter);

                if ($isFirstRow) {
                    foreach ($rowData as $index => $header) {
                        $headerLower = strtolower(trim($header));
                        if ($headerLower === 'designation') {
                            $designationCol = $index;
                        }
                        if ($headerLower === 'qtu') {
                            $qtuCol = $index;
                        }
                    }
                    $isFirstRow = false;

                    continue;
                }

                if ($designationCol === -1 || $qtuCol === -1) {
                    return back()->with('error', 'Required columns "Designation" and "Qtu" not found.');
                }

                if (! isset($rowData[$designationCol]) || ! isset($rowData[$qtuCol])) {
                    continue;
                }

                $designation = trim($rowData[$designationCol]);
                $qtu = trim($rowData[$qtuCol]);

                if (empty($designation)) {
                    continue;
                }

                $isDuplicate = false;
                foreach ($validRows as $vr) {
                    if (strtolower($vr['designation']) === strtolower($designation)) {
                        $isDuplicate = true;
                        break;
                    }
                }

                if ($isDuplicate || ! is_numeric($qtu) || $qtu <= 0) {
                    $invalidRows[] = [
                        'designation' => $designation,
                        'qtu' => $qtu,
                        'reason' => $isDuplicate ? 'Duplicate Product Name' : 'Invalid Qtu',
                    ];
                } else {
                    $validRows[] = [
                        'designation' => $designation,
                        'qtu' => (int) $qtu,
                    ];
                }
            }

            // Store preview in session cache
            $importId = uniqid('import_');
            cache()->put($importId, $validRows, now()->addMinutes(30));

            return view('admin.catalog.preview', compact('validRows', 'invalidRows', 'importId'));

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to parse file: '.$e->getMessage());
        }
    }

    public function importCatalog(Request $request)
    {
        $request->validate([
            'import_id' => 'required',
        ]);

        $validRows = cache()->get($request->import_id);

        if (! $validRows) {
            return redirect()->route('admin.catalog.index')->with('error', 'Import session expired. Please upload again.');
        }

        try {
            DB::transaction(function () use ($validRows) {
                // Clear old orders and order items
                DB::table('order_items')->delete();
                DB::table('orders')->delete();

                // Clear and insert products
                DB::table('products')->delete();

                $chunks = array_chunk($validRows, 500);
                foreach ($chunks as $chunk) {
                    $insertData = array_map(function ($row) {
                        return [
                            'designation' => $row['designation'],
                            'qtu' => $row['qtu'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }, $chunk);
                    DB::table('products')->insert($insertData);
                }
            });

            cache()->forget($request->import_id);

            return redirect()->route('admin.catalog.index')->with('success', 'Catalog replaced successfully.');

        } catch (\Exception $e) {
            return redirect()->route('admin.catalog.index')->with('error', 'Import failed: '.$e->getMessage());
        }
    }

    // Worker Management
    public function index()
    {
        $workers = User::where('role', 'worker')->get();

        return view('admin.workers.index', compact('workers'));
    }

    public function create()
    {
        return view('admin.workers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'password' => 'required|string|min:4',
        ]);

        User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'role' => 'worker',
            'active' => true,
        ]);

        return redirect()->route('admin.workers.index')->with('success', 'Worker created.');
    }

    public function edit(User $worker)
    {
        return view('admin.workers.edit', compact('worker'));
    }

    public function update(Request $request, User $worker)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($worker->id)],
            'active' => 'boolean',
        ]);

        $data = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'active' => $request->has('active'),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $worker->update($data);

        return redirect()->route('admin.workers.index')->with('success', 'Worker updated.');
    }

    public function destroy(User $worker)
    {
        $worker->delete();

        return redirect()->route('admin.workers.index')->with('success', 'Worker deleted.');
    }
}
