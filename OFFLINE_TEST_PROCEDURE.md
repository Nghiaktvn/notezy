# Notezy Offline Test Procedure

Use Chrome DevTools > Network > Offline, or physically disconnect the network.
Run these checks after logging in once while online so the app shell and notes are cached.

| # | Scenario | Expected result |
|---|----------|-----------------|
| 1 | Online: open `index_notezy.php` | Service worker registers, notes snapshot is saved to IndexedDB. |
| 2 | Offline: refresh `index_notezy.php` | Cached app shell opens; cached notes render from IndexedDB. |
| 3 | Offline: create note from the add-note modal | UI says `Saved locally`; note appears in offline list; `sync_queue` has `CREATE_NOTE`. |
| 4 | Offline: edit title/content in `edit_note.php` | UI says `Saved locally`; `sync_queue` has `UPDATE_NOTE` with `client_updated_at`. |
| 5 | Offline: delete note | Note is marked deleted locally; `sync_queue` has `DELETE_NOTE`; queue item is not removed early. |
| 6 | Offline: pin/unpin note | Pin state changes locally; `sync_queue` has `UPDATE_NOTE` with `pinned`. |
| 7 | Offline: add/remove label via offline API helpers | `sync_queue` has `ADD_LABEL` / `REMOVE_LABEL`; local note labels update. |
| 8 | Close/reopen browser while offline | IndexedDB data and sync queue persist. |
| 9 | Go online again | Badge changes to `SYNCING`, queue is processed in creation order. |
| 10 | Successful sync | Queue items are removed only after server success; badge changes to `SYNCED`. |
| 11 | API/network failure during sync | Queue item remains with increased `retryCount` and `nextRetryAt`; badge shows `SYNC ERROR`. |
| 12 | Conflict: server `updated_at` newer than client | API returns HTTP 409; UI shows "Note has been changed on another device." with Keep Server / Keep My choices. |
| 13 | Duplicate prevention | Offline-created notes use temp IDs until server returns real `note_id`; temp local record is replaced after success. |

Useful DevTools checks:

```text
Application > IndexedDB > notezy_offline_db > notes
Application > IndexedDB > notezy_offline_db > sync_queue
Application > Service Workers > sw.js
Application > Cache Storage > notezy-v3-static / notezy-v3-dynamic
```

