# Copied Section - Logic and Flow Explanation

## Overview
This document explains how the unassigned subjects logic works for **copied/cloned sections** (e.g., BSIT 1-A from 2026-2027 2nd Semester copied from 2025-2026 2nd Semester).

## Database Structure

### Section Relationship Chain
```
Section (sec_id) 
  → Class (class_id, sy_id, curr_id, class_lvl, class_term)
    → School Year (sy_id, sy_name, sy_year)
    → Curriculum (curr_id, curr_name, dept_id)
  → Program (program_id) [optional, direct link]
```

### Example: BSIT 1-A Sections
- **Original Section (2025-2026 2nd Semester)**: `sec_id = 32`, `class_id = 10`, `sy_id = 8`
- **Copied Section (2026-2027 2nd Semester)**: `sec_id = 56`, `class_id = 16`, `sy_id = 8` (same SY in dump, but could be different)
  - Note: May have different `class_id` but same or different `sy_id` depending on copy operation

## Complete Flow: How Unassigned Subjects Are Determined

### Step 1: Section Selection (Frontend → Backend)
**File**: `assets/js/schedule_management.js`

When a user clicks on a section button (e.g., BSIT 1-A from 2026-2027 2nd Semester):
1. **Event**: `handleClassButtonClick()` is triggered
2. **Action**: Extracts section info (sec_id, program_id, year_level, etc.)
3. **API Call**: Calls `get_unassigned_subjects.php?section=56&sy=11&term=2`

**Key Code Location**: `schedule_management.js` lines ~5048-5090

```javascript
function handleClassButtonClick(button, section) {
    // Sets section filter
    $('#sectionFilter').val(section.sec_id);
    
    // Builds filters
    const filters = {
        sy: $('#syFilter').val() || '',
        term: window.currentActiveTerm || '',
        program: section.program_id || '',
        year_level: section.year_level || '',
        section: section.sec_id
    };
    
    // Loads unassigned subjects
    loadUnassignedSubjects(filters);
}
```

### Step 2: Get Section Information (Backend)
**File**: `admin/management/get_unassigned_subjects.php` lines 66-79

The API receives the `section` parameter and queries:

```sql
SELECT 
    c.curr_id as class_curr_id,  -- Curriculum ID from the class
    c.dept_id,                    -- Department ID
    sec.program_id,               -- Program ID (BSIT = 30)
    cl.class_lvl as year_level,   -- Year level (1, 2, 3, 4)
    cl.class_id                   -- Class ID
FROM section sec
JOIN class cl ON sec.class_id = cl.class_id
JOIN curriculum c ON cl.curr_id = c.curr_id
WHERE sec.sec_id = ?
```

**Result**: Gets section's program, year level, department, and **class curriculum**.

**Important Note**: For copied sections, this query is the same - it just uses the new `sec_id` and `class_id`.

### Step 3: Determine Curriculum to Use (Critical Decision Point)
**File**: `get_unassigned_subjects.php` lines 81-113

**Priority Order**:
1. **Mapping Table** (`program_year_level_curriculum`) - **Takes Priority**
   - Queries: `SELECT curr_id FROM program_year_level_curriculum WHERE program_id = ? AND year_level = ?`
   - Why: Ensures consistency with Subjects tab (uses same mapping)
   - Example: BSIT Year 1 → `curr_id = 38` (after our fix)

2. **Class Curriculum** (Fallback)
   - Uses: `class_curr_id` from Step 2
   - Why: Backward compatibility if mapping table entry doesn't exist

**Key Logic**:
```php
if ($mapping_table_exists && !empty($section['program_id']) && !empty($section['year_level'])) {
    // Get from mapping table
    $curr_id_to_use = mapping_table_curr_id;
} else {
    // Fallback to class curriculum
    $curr_id_to_use = $section['class_curr_id'];
}
```

**For Copied Sections**:
- **Same Program ID**: Copied section still has `program_id = 30` (BSIT)
- **Same Year Level**: Copied section still has `year_level = 1`
- **Same Mapping**: Uses same mapping table entry → Same curriculum (38)
- **Different Class**: May have different `class_id` and `class_curr_id`, but mapping takes priority

**This is why the fix worked**: The mapping table now correctly points to curriculum 38 for BSIT Year 1, regardless of which section/class is used.

### Step 4: Filter Subjects by Criteria
**File**: `get_unassigned_subjects.php` lines 116-178

Subjects are filtered using these criteria (all must match):

1. **Curriculum**: `s.curr_id = ?` (from Step 3)
2. **Year Level**: `s.subj_lvl = ?` (from section's year level)
3. **Term**: `s.subj_term = ?` (from term filter, e.g., Term 2)
4. **Program**: `s.program_id = ? OR s.program_id IS NULL` (section's program or shared subjects)
5. **Department**: `curr.dept_id = ?` (for security/filtering)

**Query Structure**:
```sql
SELECT DISTINCT
    s.subj_id,
    s.subj_code,
    s.subj_desc,
    s.subj_lec,      -- Required lecture hours
    s.subj_lab,      -- Required lab hours
    s.subj_category
FROM subject s
JOIN curriculum curr ON s.curr_id = curr.curr_id
WHERE s.curr_id = 38          -- From mapping table
  AND s.subj_lvl = 1          -- Year level 1
  AND s.subj_term = 2         -- Term 2
  AND (s.program_id = 30 OR s.program_id IS NULL)  -- BSIT or shared
  AND curr.dept_id = 1        -- Department filter
```

**For Copied Sections**: Same filters apply, but schedules are checked for the **new section** (different `sec_id`).

### Step 5: Check Scheduled Hours for Each Subject
**File**: `get_unassigned_subjects.php` lines 242-328

For each matching subject, the system checks if it has **enough scheduled hours** for **this specific section** in **this specific school year and term**.

**Schedule Check Query** (built dynamically):
```sql
SELECT 
    SUM(CASE WHEN schd_type = 'Lec' THEN schd_min / 60.0 ELSE 0 END) as lec_hours,
    SUM(CASE WHEN schd_type = 'Lab' THEN schd_min / 60.0 ELSE 0 END) as lab_hours,
    COUNT(CASE WHEN schd_type = 'Lec' THEN 1 END) as lec_count,
    COUNT(CASE WHEN schd_type = 'Lab' THEN 1 END) as lab_count
FROM schedule 
WHERE subj_id = ? 
  AND sec_id = ?           -- THE COPIED SECTION ID (e.g., 56)
  AND sy_id = ?            -- THE NEW SCHOOL YEAR ID (e.g., 11)
  AND schd_term = ?        -- THE TERM (e.g., 2)
  AND schd_status = 'Active'
```

**Business Rules**:
1. **Lecture Hours**: Must meet `subj_lec` requirement
2. **Lab Hours**: Must meet `subj_lab` requirement
3. **Multiple Classes Rule**: If `subj_lec >= 3` or `subj_lab >= 3`, require **at least 2 classes** (not just total hours)
   - Example: IT 123 (2 Lec, 3 Lab) → Needs 1 Lec class + 1 Lab class (if Lab < 3) or 2 Lab classes (if Lab >= 3)

**Subject is "Fully Scheduled" if**:
- `lec_hours >= subj_lec` AND `(subj_lec < 3 OR lec_count >= 2)` (for Lec)
- `lab_hours >= subj_lab` AND `(subj_lab < 3 OR lab_count >= 2)` (for Lab)

**Subject appears as "Unassigned" if**:
- NOT fully scheduled (either Lec or Lab requirements not met)

**For Copied Sections**:
- **Key Difference**: Schedules are checked for the **new section ID** (`sec_id = 56`) in the **new school year** (`sy_id = 11`)
- **If no schedules exist** for the copied section → All matching subjects appear as unassigned
- **If schedules were copied** → Only subjects without enough hours appear as unassigned

### Step 6: Return Results (Backend → Frontend)
**File**: `get_unassigned_subjects.php` lines ~400-500

The API returns a JSON response:

```json
{
    "success": true,
    "data": [
        {
            "subj_id": 163,
            "subj_code": "IT 123",
            "subj_desc": "Introduction to Human Computer Interaction",
            "subj_lec": 2,
            "subj_lab": 3,
            "subj_category": "Major"
        },
        // ... more unassigned subjects
    ],
    "count": 8
}
```

### Step 7: Display Unassigned Subjects (Frontend)
**File**: `schedule_management.js` lines ~842-884

The frontend receives the response and:
1. **Checks**: `if (data.success && data.data.length > 0)`
2. **Displays**: Calls `displayUnassignedSubjects(data.data)`
3. **Shows Container**: Calls `showUnassignedSubjectsContainer()`
4. **Renders**: Shows list of unassigned subjects in the UI

## Key Differences for Copied Sections

### 1. Section ID Changes
- **Original**: `sec_id = 32`
- **Copied**: `sec_id = 56`
- **Impact**: Schedule checks use new section ID

### 2. School Year ID May Change
- **Original**: `sy_id = 8` (2025-2026 2nd Semester)
- **Copied**: `sy_id = 11` (2026-2027 2nd Semester)
- **Impact**: Schedule checks use new school year ID

### 3. Class ID May Change
- **Original**: `class_id = 10`
- **Copied**: `class_id = 16`
- **Impact**: **NONE** for unassigned subjects logic (curriculum comes from mapping table, not class)

### 4. Curriculum Determination
- **Uses Mapping Table**: `program_id = 30`, `year_level = 1` → `curr_id = 38`
- **Same for Both**: Original and copied sections use **same mapping** → **same curriculum**
- **This is why the fix works for all sections**: Once mapping is correct, all BSIT Year 1 sections (original or copied) use curriculum 38

### 5. Schedule Checks
- **Original Section**: Checks schedules where `sec_id = 32`, `sy_id = 8`, `term = 2`
- **Copied Section**: Checks schedules where `sec_id = 56`, `sy_id = 11`, `term = 2`
- **Impact**: Copied section may have no schedules initially → Shows all subjects as unassigned

## Flow Diagram

```
User Clicks BSIT 1-A (2026-2027 2nd Sem)
    ↓
Frontend: handleClassButtonClick()
    ↓
API Call: get_unassigned_subjects.php?section=56&sy=11&term=2
    ↓
Backend: Get Section Info (sec_id=56, class_id=16, program_id=30, year_level=1)
    ↓
Backend: Check Mapping Table (program_id=30, year_level=1 → curr_id=38) ✅
    ↓
Backend: Filter Subjects (curr_id=38, subj_lvl=1, subj_term=2, program_id=30)
    ↓
Backend: Check Schedules (sec_id=56, sy_id=11, term=2) for each subject
    ↓
Backend: Filter Out Fully Scheduled Subjects
    ↓
Backend: Return Unassigned Subjects JSON
    ↓
Frontend: Display Unassigned Subjects List
```

## Important Points for Copied Sections

### ✅ What Works the Same
1. **Curriculum Determination**: Uses mapping table (same for all BSIT Year 1 sections)
2. **Subject Filtering**: Same curriculum, year level, term filters
3. **Business Rules**: Same Lec/Lab hour requirements and multiple classes rule

### ⚠️ What's Different
1. **Section ID**: New section ID for schedule checks
2. **School Year ID**: New school year ID for schedule checks
3. **Initial State**: Copied section starts with **no schedules** → All subjects appear as unassigned until scheduled

### 🔑 Why Copied Sections Work Correctly After the Fix

**Before Fix**:
- Mapping table: BSIT Year 1 → `curr_id = 43` (wrong)
- Class curriculum: `curr_id = 38` (correct, but ignored)
- Result: Shows 0 subjects (curriculum 43 has no Year 1 Term 2 subjects)

**After Fix**:
- Mapping table: BSIT Year 1 → `curr_id = 38` (correct) ✅
- Class curriculum: `curr_id = 38` (also correct)
- Result: Shows 8 unassigned subjects (curriculum 38 has 8 Year 1 Term 2 subjects)

**For Copied Sections**:
- Uses **same mapping** (program_id=30, year_level=1) → Gets `curr_id = 38` ✅
- Checks **different schedules** (sec_id=56, sy_id=11) → Shows subjects that aren't scheduled yet
- **Works correctly** because curriculum determination is consistent across all sections

## Testing Copied Sections

To verify a copied section works correctly:

1. **Access Diagnostic Script**:
   ```
   http://yourdomain.com/admin/management/analyze_copied_section.php?sy_id=11&term=2
   ```

2. **Check Expected Results**:
   - `curriculum_used` should be `38` (from mapping table)
   - `matching_subjects` should show 8 Year 1 Term 2 subjects
   - `unassigned_subjects` should show subjects without schedules for that section/SY/term

3. **Check Console Output**:
   - Click on the copied BSIT 1-A section
   - Check browser console for diagnostic information
   - Verify `curriculum_id: 38` in the response

## Summary

**The unassigned subjects logic works identically for copied sections** because:
1. **Curriculum is determined from mapping table** (not class) → Same for all sections with same program/year level
2. **Subject filtering uses curriculum + year level + term** → Same subjects match
3. **Schedule checking uses section-specific parameters** → Each section checks its own schedules

**The fix we applied (updating mapping table) ensures all BSIT Year 1 sections** (original or copied) use the correct curriculum (38), which has the proper Year 1 Term 2 subjects.
