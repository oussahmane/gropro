<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_import_preview_and_replace()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        // Add existing product
        Product::create(['designation' => 'Old Product', 'qtu' => 10]);

        $tsvContent = "Designation\tQtu\tStock\n" .
                      "CAFE NIZIERE ROUGE\t5\t20.400\n" .
                      "TOM 1001/400\t24\t194.000\n" .
                      "TOM CAB 1KG\t1\t69.000\n" .
                      "INVALID PROD\t-5\t0\n" . 
                      "EMPTY QTU\t\t0\n" . 
                      "DUPLICATE\t10\t0\n" . 
                      "DUPLICATE\t12\t0\n";
                      
        $file = UploadedFile::fake()->createWithContent('catalog.txt', $tsvContent);

        $this->actingAs($admin);

        // Test Preview
        $response = $this->post(route('admin.catalog.preview'), [
            'catalog_file' => $file,
        ]);
        
        $response->assertStatus(200);
        $response->assertViewHas('validRows');
        $response->assertViewHas('invalidRows');
        
        $validRows = $response->viewData('validRows');
        $invalidRows = $response->viewData('invalidRows');
        $importId = $response->viewData('importId');
        
        $this->assertCount(4, $validRows); // CAFE, TOM 400, TOM 1KG, first DUPLICATE
        $this->assertCount(3, $invalidRows); // INVALID (-5), EMPTY, second DUPLICATE

        // Test Import
        $response2 = $this->post(route('admin.catalog.import'), [
            'import_id' => $importId,
        ]);

        $response2->assertRedirect(route('admin.catalog.index'));
        $response2->assertSessionHas('success');

        // Verify DB
        $this->assertDatabaseMissing('products', ['designation' => 'Old Product']);
        $this->assertDatabaseHas('products', ['designation' => 'CAFE NIZIERE ROUGE', 'qtu' => 5]);
        $this->assertEquals(4, Product::count());
    }
}
