# Single-Cell Building System

## Overview
Changed from multi-cell buildings (JSON array of cells) to single-cell buildings (x,y coordinates). Each building now occupies exactly one cell on the 10x10 grid.

## Database Changes

### Migration: `update_city_objects_single_cell`
- **Removed**: `cells` JSON column
- **Added**: `x` TINYINT(3) UNSIGNED column
- **Added**: `y` TINYINT(3) UNSIGNED column
- **Migration logic**: Converts existing single-cell objects from JSON to x,y

### CityObject Model
- **Updated fillable**: Removed `cells`, added `x`, `y`
- **Updated casts**: Removed `cells` array cast

## API Changes

### POST `/api/city-objects/save`
**Request format changed:**
```json
// OLD (multi-cell)
{
  "objects": [{
    "parcel_id": 1,
    "object_type": "house",
    "cells": [{"x": 0, "y": 0}],
    "properties": {...}
  }]
}

// NEW (single-cell)
{
  "objects": [{
    "parcel_id": 1,
    "object_type": "house",
    "x": 0,
    "y": 0,
    "properties": {...}
  }]
}
```

**Validation updated:**
- Removed: `objects.*.cells` array validation
- Added: `objects.*.x` and `objects.*.y` integer validation (0-9)
- Added: Cell overlap check (no two buildings on same x,y)

### GET `/api/city-objects`
- Returns objects with `x`, `y` coordinates instead of `cells` array
- `build_seconds` calculation simplified (no cell counting)

## Frontend Changes

### ParcelEditor.vue
- **Selection**: Now selects single cell instead of multi-cell rectangle
- **Data sending**: Sends `x`, `y` instead of `cells` array
- **Object detection**: Simplified `isCellInObject()` to check single x,y match
- **UI**: Grid selection now allows only single cell selection

## Benefits

### ✅ Simpler Architecture
- One building = one database record = one cell
- No complex JSON parsing or cell array management
- Easier collision detection (simple x,y check)

### ✅ Better Performance
- No JSON operations in database
- Faster queries (no array operations)
- Simpler frontend logic

### ✅ Future-Proof
- Easy to extend to multi-cell buildings later if needed
- Clean separation of concerns
- Better for pathfinding and game mechanics

## Migration Notes

### For Existing Data
- Migration automatically converts single-cell objects
- Multi-cell objects will lose extra cells (assumes single-cell usage)
- User should clear existing data before migration if multi-cell was used

### Rollback
- Migration is reversible
- Converts back to JSON cells format

## Testing

### Frontend
- Select single cell → build modal opens
- Select multiple cells → should not work (single selection only)
- Build completes → object appears on correct x,y

### Backend
- API accepts x,y instead of cells
- Validation prevents duplicate x,y on same parcel
- Build time calculation works correctly

## Future Enhancements

Consider for multi-cell buildings:
1. Add `width`, `height` columns for rectangle buildings
2. Update collision detection for rectangles
3. Modify frontend selection for rectangle selection
4. Keep single-cell as default for simplicity