Second pass completed with focus on deeper dashboard interactivity and dynamic syncing.

What changed
- Added JSON dashboard feed at /api/driver/dashboard
- Added JSON parcel tracking feed at /api/user/parcels
- Added API notification refresh at /api/notifications
- Driver dashboard now syncs through API polling instead of Inertia reloads
- Driver accept / status update / availability flows now use async axios calls with optimistic UI updates
- User parcels page now syncs live via API polling and manual sync action
- Authenticated layout notifications now refresh via API instead of page reloads
- Removed remaining router.reload calls from key profile/routes pages so updates rely less on full reload patterns
- Backend controllers now return JSON for driver workflow actions when requested asynchronously

Areas improved
- deeper dashboard interactivity
- reduced reload-based patterns
- stronger end-to-end sync for driver/customer parcel workflows

Recommended next pass
- move profile updates to dedicated JSON endpoints so header user identity updates instantly after profile edits
- add broadcasting or websockets later for true push updates instead of polling
- unify admin pages around the same async data model
