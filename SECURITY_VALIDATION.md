# Backend Security Validations

## CityController::save() Validations

### 1. Authentication Check
- Verifies user is logged in via session
- Returns 401 if not authenticated

### 2. Parcel Ownership Validation
- Checks that the parcel belongs to the current user
- Returns 403 if user tries to build on someone else's parcel
- Prevents users from manipulating parcel_id in frontend

### 3. Object Type Validation
- Verifies the object_type exists in `object_types` table
- Returns 400 if invalid object type is sent
- Prevents users from creating fake object types with manipulated build times

### 4. Cell Coordinates Validation
- Already validated via Laravel validation rules (0-9 range)
- Additional runtime check ensures x,y are within 0-9 bounds
- Returns 400 if coordinates are out of bounds

### 5. Workers Validation (NEW)
- **Level & Count Check**: Verifies user actually has the claimed number of workers at the specified level
- **Query**: Checks `people` table for `user_id`, `level`, and available `count`
- **Returns 400** if user claims more workers than they have
- **Example**: User claims 3 workers at level 2, but only has 2 in database → request rejected
- **Prevents**: Frontend manipulation to reduce build time with fake workers

### 6. Build Time Calculation
- Build time is ALWAYS calculated on backend
- Frontend `displayedTimes` is only for UI preview
- Backend uses:
  - Base time from `object_types.build_time_minutes`
  - Reduction: `level × count × 60 seconds`
  - Minimum: 60 seconds (1 minute)
- Formula: `max(60, base_seconds - (level × count × 60))`

## What Cannot Be Manipulated

❌ **User cannot:**
- Build on parcels they don't own
- Use object types that don't exist in database
- Claim more workers than they have in `people` table
- Set custom build times (always calculated server-side)
- Place objects outside 10×10 grid
- Bypass minimum 1-minute build time

✅ **Server always:**
- Validates parcel ownership
- Checks worker availability in database
- Calculates build time from database values
- Enforces grid boundaries
- Applies minimum build time cap

## Future Enhancements

Consider adding:
1. **Worker reservation system**: Mark workers as "busy" during construction
2. **Cell overlap detection**: Prevent multiple objects on same cells
3. **Rate limiting**: Prevent spam building
4. **Audit logging**: Track all building attempts for security review
