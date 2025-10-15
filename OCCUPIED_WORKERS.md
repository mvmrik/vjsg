# Occupied Workers System

## Overview
System for tracking occupied workers during building construction. Workers can be used for multiple buildings simultaneously, but cannot exceed available count.

## Database Schema

### `occupied_workers` table
- `user_id` (foreign key to users)
- `level` (worker level)
- `count` (number of workers occupied)
- `occupied_until` (timestamp when workers become free)
- `city_object_id` (foreign key to city_objects - which building uses them)

## How It Works

### 1. Starting Construction
- When building starts, `OccupiedWorker` record is created
- Record links workers to specific building and occupation end time

### 2. Checking Available Workers
- `PeopleController::index` returns only free workers (total - occupied)
- Frontend shows only available workers in selects

### 3. Completing Construction
- `CityController::index` checks for completed buildings (`ready_at` null)
- Deletes corresponding `occupied_workers` records
- Workers become available again

### 4. Validation
- Backend validates user has enough free workers before allowing construction
- Prevents over-allocation of workers

## API Changes

### `/api/people` (GET)
- Now returns `total` and `by_level` as **free** workers only
- Occupied workers are subtracted from totals

### `/api/city-objects/save` (POST)
- Creates `OccupiedWorker` record when workers are used
- Validates worker availability before construction

### `/api/city-objects` (GET)
- Automatically frees workers for completed buildings
- Updates worker availability

## Frontend Changes

### ParcelEditor.vue
- Worker selects show only available counts
- Cannot select more workers than available
- Button disabled if insufficient workers

## Example Flow

1. User has 5 level-1 workers
2. Starts building A using 2 level-1 workers
   - `occupied_workers` record created: level=1, count=2, city_object_id=A
   - Available level-1 workers: 3
3. Starts building B using 2 level-1 workers
   - `occupied_workers` record created: level=1, count=2, city_object_id=B
   - Available level-1 workers: 1
4. Building A completes
   - `occupied_workers` record for A deleted
   - Available level-1 workers: 3
5. Building B completes
   - `occupied_workers` record for B deleted
   - Available level-1 workers: 5

## Performance
- Uses indexed queries for fast availability checks
- Scales well with multiple concurrent buildings per user
- Automatic cleanup prevents orphaned records