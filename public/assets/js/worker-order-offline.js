/**
 * Per-order IndexedDB draft persistence + reconnect sync for worker order edit.
 * Scoped by orderId — separate drafts for separate browser tabs / orders.
 */
(function (global) {
    'use strict';

    const DB_NAME = 'gropro-worker-order-drafts';
    const DB_VERSION = 1;
    const STORE = 'drafts';

    const STATUS = {
        SAVING: 'saving',
        SAVED: 'saved',
        OFFLINE: 'offline',
        SYNCING: 'syncing',
        SYNC_FAILED: 'sync_failed',
    };

    const STATUS_LABELS = {
        saving: 'Enregistrement…',
        saved: 'Enregistré',
        offline: 'Hors ligne',
        syncing: 'Synchronisation…',
        sync_failed: 'Échec de sync',
    };

    function openDb() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = function () {
                const db = req.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    db.createObjectStore(STORE, { keyPath: 'orderId' });
                }
            };
            req.onsuccess = function () {
                resolve(req.result);
            };
            req.onerror = function () {
                reject(req.error);
            };
        });
    }

    function idbGet(orderId) {
        return openDb().then((db) => new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, 'readonly');
            const req = tx.objectStore(STORE).get(orderId);
            req.onsuccess = function () {
                resolve(req.result || null);
            };
            req.onerror = function () {
                reject(req.error);
            };
        }));
    }

    function idbPut(record) {
        return openDb().then((db) => new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, 'readwrite');
            tx.objectStore(STORE).put(record);
            tx.oncomplete = function () {
                resolve(record);
            };
            tx.onerror = function () {
                reject(tx.error);
            };
        }));
    }

    function idbDelete(orderId) {
        return openDb().then((db) => new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, 'readwrite');
            tx.objectStore(STORE).delete(orderId);
            tx.oncomplete = function () {
                resolve();
            };
            tx.onerror = function () {
                reject(tx.error);
            };
        }));
    }

    function uuid() {
        if (global.crypto && typeof global.crypto.randomUUID === 'function') {
            return global.crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = (Math.random() * 16) | 0;
            const v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    function isOnline() {
        return global.navigator.onLine !== false;
    }

    /**
     * @param {object} options
     * @param {number} options.orderId
     * @param {number} options.contentRevision
     * @param {string} options.updateUrl
     * @param {string} options.submitUrl
     * @param {HTMLElement|null} options.statusEl
     * @param {function} options.getSnapshot - () => { customer_name, notes, items }
     * @param {function} options.applySnapshot - (snap) => void
     * @param {function} [options.onConflict]
     * @param {function} [options.onSyncFailed]
     */
    function createOrderOfflineSync(options) {
        const orderId = options.orderId;
        let contentRevision = Number(options.contentRevision) || 0;
        let draft = null;
        let syncInFlight = false;
        let syncAgain = false;
        let saveChain = Promise.resolve();
        let retryTimer = null;
        let retryAttempt = 0;

        function setUiStatus(status, detail) {
            if (!options.statusEl) {
                return;
            }
            const label = STATUS_LABELS[status] || status;
            options.statusEl.dataset.status = status;
            options.statusEl.textContent = detail ? label + ' — ' + detail : label;
            options.statusEl.className = 'order-sync-status order-sync-status--' + status;
        }

        function defaultRecord() {
            return {
                orderId: orderId,
                customer_name: '',
                notes: '',
                items: [],
                contentRevision: contentRevision,
                pendingSync: false,
                pendingSubmit: false,
                updateIdempotencyKey: null,
                submitIdempotencyKey: null,
                lastLocalChangeAt: null,
                lastError: null,
                uiStatus: STATUS.SAVED,
            };
        }

        function persist(record) {
            draft = record;
            setUiStatus(record.uiStatus, record.lastError || null);
            return idbPut(record);
        }

        function snapshotToRecord(snap, extras) {
            const base = draft || defaultRecord();
            return Object.assign({}, base, {
                customer_name: snap.customer_name,
                notes: snap.notes,
                items: snap.items,
                contentRevision: contentRevision,
            }, extras || {});
        }

        function scheduleRetry() {
            if (retryTimer) {
                return;
            }
            const delay = Math.min(30000, 1000 * Math.pow(2, retryAttempt));
            retryAttempt += 1;
            retryTimer = global.setTimeout(function () {
                retryTimer = null;
                flushSync();
            }, delay);
        }

        function clearRetry() {
            if (retryTimer) {
                global.clearTimeout(retryTimer);
                retryTimer = null;
            }
            retryAttempt = 0;
        }

        /**
         * Save draft locally immediately, then attempt server sync when online.
         */
        function saveLocalAndQueueSync(opts) {
            const snap = options.getSnapshot();
            const wantSubmit = !!(opts && opts.queueSubmit);

            saveChain = saveChain.then(async function () {
                const prev = draft || (await idbGet(orderId)) || defaultRecord();
                const next = snapshotToRecord(snap, {
                    pendingSync: true,
                    pendingSubmit: wantSubmit || prev.pendingSubmit,
                    lastLocalChangeAt: new Date().toISOString(),
                    lastError: null,
                    // Always new key when content changes so in-flight responses stay tied to their payload.
                    updateIdempotencyKey: uuid(),
                    submitIdempotencyKey: (wantSubmit || prev.pendingSubmit)
                        ? (prev.submitIdempotencyKey || uuid())
                        : null,
                    uiStatus: isOnline() ? STATUS.SAVING : STATUS.OFFLINE,
                });

                await persist(next);

                if (!isOnline()) {
                    next.uiStatus = STATUS.OFFLINE;
                    await persist(next);
                    return;
                }

                next.uiStatus = STATUS.SYNCING;
                await persist(next);
                flushSync();
            }).catch(function (err) {
                console.error('IndexedDB save failed', err);
                setUiStatus(STATUS.SYNC_FAILED, 'stockage local');
            });

            return saveChain;
        }

        async function flushSync() {
            if (syncInFlight) {
                syncAgain = true;
                return;
            }
            if (!isOnline()) {
                if (draft && draft.pendingSync) {
                    draft.uiStatus = STATUS.OFFLINE;
                    await persist(draft);
                }
                return;
            }

            syncInFlight = true;
            try {
                let record = draft || (await idbGet(orderId));
                if (!record || (!record.pendingSync && !record.pendingSubmit)) {
                    clearRetry();
                    if (record) {
                        record.uiStatus = STATUS.SAVED;
                        await persist(record);
                    } else {
                        setUiStatus(STATUS.SAVED);
                    }
                    return;
                }

                record.uiStatus = STATUS.SYNCING;
                record.lastError = null;
                await persist(record);

                if (record.pendingSync) {
                    const keyForRequest = record.updateIdempotencyKey || uuid();
                    record.updateIdempotencyKey = keyForRequest;
                    await persist(record);

                    const formData = options.buildFormData(record);
                    formData.append('base_revision', String(record.contentRevision));
                    formData.append('idempotency_key', keyForRequest);

                    const res = await fetch(options.updateUrl, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'Idempotency-Key': keyForRequest,
                        },
                    });

                    let data = {};
                    try {
                        data = await res.json();
                    } catch (e) {
                        data = {};
                    }

                    // Re-read after await — local edits may have changed the draft.
                    record = draft || (await idbGet(orderId)) || record;

                    if (res.status === 409 || data.conflict) {
                        record.pendingSync = true;
                        record.uiStatus = STATUS.SYNC_FAILED;
                        record.lastError = data.message || 'conflit serveur';
                        await persist(record);
                        clearRetry();
                        if (typeof options.onConflict === 'function') {
                            options.onConflict(data);
                        }
                        if (typeof options.onSyncFailed === 'function') {
                            options.onSyncFailed(data);
                        }
                        return;
                    }

                    if (!res.ok || !data.success) {
                        record.uiStatus = STATUS.SYNC_FAILED;
                        record.lastError = 'réseau / serveur';
                        await persist(record);
                        if (typeof options.onSyncFailed === 'function') {
                            options.onSyncFailed(data);
                        }
                        scheduleRetry();
                        return;
                    }

                    contentRevision = Number(data.content_revision);
                    record.contentRevision = contentRevision;
                    // Only clear pending if this response matches the latest queued key.
                    if (record.updateIdempotencyKey === keyForRequest) {
                        record.pendingSync = false;
                        record.updateIdempotencyKey = null;
                    } else {
                        record.pendingSync = true;
                    }
                    record.lastError = null;
                    await persist(record);
                }

                // Re-read in case local edits landed during update
                record = draft || (await idbGet(orderId));
                if (record && record.pendingSync) {
                    // Newer local edits queued — loop again
                } else if (record && record.pendingSubmit) {
                    const submitBody = new FormData();
                    const csrf = options.getCsrfToken && options.getCsrfToken();
                    if (csrf) {
                        submitBody.append('_token', csrf);
                    }
                    submitBody.append('base_revision', String(record.contentRevision));
                    if (record.submitIdempotencyKey) {
                        submitBody.append('idempotency_key', record.submitIdempotencyKey);
                    }

                    const res = await fetch(options.submitUrl, {
                        method: 'POST',
                        body: submitBody,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'Idempotency-Key': record.submitIdempotencyKey || '',
                        },
                    });

                    let data = {};
                    try {
                        data = await res.json();
                    } catch (e) {
                        data = {};
                    }

                    if (res.status === 409 || data.conflict) {
                        record.uiStatus = STATUS.SYNC_FAILED;
                        record.lastError = data.message || 'conflit soumission';
                        await persist(record);
                        clearRetry();
                        if (typeof options.onConflict === 'function') {
                            options.onConflict(data);
                        }
                        if (typeof options.onSyncFailed === 'function') {
                            options.onSyncFailed(data);
                        }
                        return;
                    }

                    if (!res.ok || !data.success) {
                        record.uiStatus = STATUS.SYNC_FAILED;
                        record.lastError = 'soumission';
                        await persist(record);
                        if (typeof options.onSyncFailed === 'function') {
                            options.onSyncFailed(data);
                        }
                        scheduleRetry();
                        return;
                    }

                    await idbDelete(orderId);
                    draft = null;
                    clearRetry();
                    setUiStatus(STATUS.SAVED);
                    if (data.redirect) {
                        global.location.href = data.redirect;
                    }
                    return;
                }

                record = draft || (await idbGet(orderId));
                if (record && !record.pendingSync && !record.pendingSubmit) {
                    record.uiStatus = STATUS.SAVED;
                    await persist(record);
                    clearRetry();
                } else if (record && (record.pendingSync || record.pendingSubmit)) {
                    // Content changed during flight, or submit still queued.
                    syncAgain = true;
                }
            } catch (err) {
                console.error('Sync failed', err);
                if (draft) {
                    draft.uiStatus = isOnline() ? STATUS.SYNC_FAILED : STATUS.OFFLINE;
                    draft.lastError = isOnline() ? 'réseau' : null;
                    await persist(draft);
                } else {
                    setUiStatus(isOnline() ? STATUS.SYNC_FAILED : STATUS.OFFLINE);
                }
                if (typeof options.onSyncFailed === 'function') {
                    options.onSyncFailed({ error: err });
                }
                if (isOnline()) {
                    scheduleRetry();
                }
            } finally {
                syncInFlight = false;
                if (syncAgain) {
                    syncAgain = false;
                    flushSync();
                }
            }
        }

        async function restoreOnLoad() {
            const stored = await idbGet(orderId);
            if (!stored) {
                const snap = options.getSnapshot();
                draft = snapshotToRecord(snap, {
                    pendingSync: false,
                    pendingSubmit: false,
                    uiStatus: STATUS.SAVED,
                    contentRevision: contentRevision,
                });
                await persist(draft);
                return { restored: false };
            }

            draft = stored;
            contentRevision = Number(stored.contentRevision) || contentRevision;

            const shouldRestore = stored.pendingSync || stored.pendingSubmit || stored.lastLocalChangeAt;
            if (shouldRestore && (stored.pendingSync || stored.pendingSubmit)) {
                options.applySnapshot({
                    customer_name: stored.customer_name,
                    notes: stored.notes,
                    items: stored.items || [],
                });
                contentRevision = Number(stored.contentRevision) || contentRevision;
                setUiStatus(stored.uiStatus || (isOnline() ? STATUS.SYNCING : STATUS.OFFLINE), stored.lastError);
                if (isOnline() && (stored.pendingSync || stored.pendingSubmit)) {
                    flushSync();
                }
                return { restored: true };
            }

            // Local copy is already synced — prefer live server snapshot from the page
            const snap = options.getSnapshot();
            draft = snapshotToRecord(snap, {
                pendingSync: false,
                pendingSubmit: false,
                updateIdempotencyKey: null,
                submitIdempotencyKey: null,
                lastError: null,
                uiStatus: STATUS.SAVED,
                contentRevision: contentRevision,
            });
            await persist(draft);
            return { restored: false };
        }

        global.addEventListener('online', function () {
            if (draft && (draft.pendingSync || draft.pendingSubmit)) {
                draft.uiStatus = STATUS.SYNCING;
                persist(draft).then(function () {
                    flushSync();
                });
            }
        });

        global.addEventListener('offline', function () {
            if (draft) {
                draft.uiStatus = STATUS.OFFLINE;
                persist(draft);
            } else {
                setUiStatus(STATUS.OFFLINE);
            }
        });

        return {
            STATUS: STATUS,
            saveLocalAndQueueSync: saveLocalAndQueueSync,
            flushSync: flushSync,
            restoreOnLoad: restoreOnLoad,
            getContentRevision: function () {
                return contentRevision;
            },
            queueSubmit: function () {
                return saveLocalAndQueueSync({ queueSubmit: true });
            },
        };
    }

    global.GroproWorkerOrderOffline = {
        STATUS: STATUS,
        createOrderOfflineSync: createOrderOfflineSync,
        idbGet: idbGet,
        idbPut: idbPut,
        idbDelete: idbDelete,
    };
})(window);
