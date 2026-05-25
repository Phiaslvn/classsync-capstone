# Unassigned Subjects Diagnostics

## Overview
This document explains the enhanced debugging and diagnostic tools added to help identify why unassigned subjects might not appear for certain sections.

## Enhanced Debug Logging

### Location
`admin/management/get_unassigned_subjects.php`

### What It Does
When no unassigned subjects are found for a section, the system now logs detailed diagnostic information including:

1. **Section Information**
   - Section ID, name, program, year level, curriculum, department

2. **Curriculum Determination**
   - Which curriculum is being used (from mapping table or class fallback)
   - Curriculum ID and name

3. **Subject Counts**
   - Total subjects in curriculum
   - Subjects by year level
   - **TERM-SPECIFIC CHECK**: Subjects matching year level + term filter
   - Breakdown by term for the year level

4. **Detailed Subject Analysis**
   - For each subject matching year level + term:
     - Subject code, description, required hours (Lec/Lab)
     - All schedules for this subject in this section, SY, and term
     - Scheduled hours breakdown (Lec/Lab hours and count)
     - Whether subject is fully scheduled or should appear as unassigned
     - Detailed reason for inclusion/exclusion

5. **Schedule Analysis**
   - Total scheduled Lec hours vs required
   - Total scheduled Lab hours vs required
   - Schedule count analysis (handles subjects needing multiple classes per week)
   - Status: Fully scheduled (excluded) vs Partially/Unscheduled (should appear)

### How to Access
Debug logs are written to PHP error log. Check your server's error log file (usually in `/var/log/php-errors.log` or configured in `php.ini`).

Look for log entries starting with:
```
=== DEBUG: No unassigned subjects found for section XXX ===
```

## Diagnostic Query Script

### Location
`admin/management/diagnose_unassigned_subjects.php`

### Usage
Access via browser or API call:
```
GET /admin/management/diagnose_unassigned_subjects.php?section_id=32&sy_id=8&term=2
```

### Parameters
- `section_id` (required): The section ID to diagnose (e.g., 32 for BSIT 1-A)
- `sy_id` (optional): School Year ID (e.g., 8)
- `term` (optional): Term number (1, 2, or 3)

### Output
Returns JSON with comprehensive diagnostic information:

```json
{
  "success": true,
  "diagnostics": {
    "section_id": 32,
    "sy_id": 8,
    "term": 2,
    "section_info": {...},
    "checks": {
      "curriculum_id": 38,
      "total_subjects_in_curriculum": 50,
      "subjects_year_level_1": 15,
      "subjects_year_level_1_term_2": 5,
      "matching_subjects": [...],
      "subject_analysis": [
        {
          "subj_id": 163,
          "subj_code": "IT 123",
          "required_lec": 2,
          "required_lab": 3,
          "scheduled_lec_hours": 2.0,
          "scheduled_lab_hours": 3.0,
          "is_fully_scheduled": true,
          "should_appear_as_unassigned": false,
          "reason": "Fully scheduled",
          "analysis": {...}
        }
      ],
      "summary": {
        "total_matching_subjects": 5,
        "should_appear_as_unassigned": 0,
        "fully_scheduled": 5,
        "expected_unassigned_count": 0
      },
      "term_breakdown": {
        "term_1": 8,
        "term_2": 5,
        "term_3": 2
      }
    }
  }
}
```

### Example Usage

**For BSIT 1-A (section 32, year level 1, term 2):**
```
http://yourdomain.com/admin/management/diagnose_unassigned_subjects.php?section_id=32&sy_id=8&term=2
```

**For BSIT 2-A (section 44, year level 2, term 2):**
```
http://yourdomain.com/admin/management/diagnose_unassigned_subjects.php?section_id=44&sy_id=8&term=2
```

## Understanding the Results

### If `expected_unassigned_count` is 0:
- ✅ **Correct behavior** - All subjects for that year level and term are already fully scheduled
- The empty result is expected and correct
- No action needed

### If `expected_unassigned_count` > 0 but no subjects appear:
- ⚠️ **Potential issue** - Subjects should appear but don't
- Check:
  1. Are subjects filtered by program correctly? (`subjects_matching_program_X`)
  2. Are subjects in the correct curriculum? (`curriculum_id`)
  3. Do subjects match the year level and term? (`subjects_year_level_X_term_Y`)
  4. Check the `subject_analysis` array for each subject's status

### If `matching_subjects` is empty:
- ⚠️ **Data issue** - No subjects exist for that year level and term in the curriculum
- This means:
  - All subjects for that year level are in a different term (check `term_breakdown`)
  - Or subjects haven't been added to the curriculum yet

## Common Scenarios

### Scenario 1: BSIT 1-A shows empty in Term 2
**Expected if:**
- All Year 1 Term 2 subjects are fully scheduled
- Or Year 1 subjects are mostly in Term 1 (check `term_breakdown`)

**Investigation:**
1. Run diagnostic script for section 32, term 2
2. Check `summary.expected_unassigned_count`
3. If 0, behavior is correct
4. If > 0, check `subject_analysis` to see why subjects aren't appearing

### Scenario 2: BSIT 2-A shows subjects in Term 2
**Expected if:**
- Year 2 Term 2 subjects exist and some are not fully scheduled

**Verification:**
1. Run diagnostic script for section 44, term 2
2. Check `summary.should_appear_as_unassigned` count
3. Should match the number of subjects shown in the UI

## Next Steps After Diagnosis

1. **If all subjects are fully scheduled** (expected_unassigned_count = 0):
   - ✅ No action needed - system is working correctly

2. **If subjects should appear but don't**:
   - Review `subject_analysis` for each subject
   - Check if schedule calculations are correct
   - Verify filter conditions in `get_unassigned_subjects.php`

3. **If no subjects exist for term**:
   - Check curriculum setup
   - Verify subjects are assigned to correct term (`subj_term` field)
   - Review `term_breakdown` to see where subjects actually are

## Testing the Fix

After making changes, test by:
1. Running diagnostic script for both sections
2. Comparing expected vs actual unassigned counts
3. Checking PHP error logs for detailed debug information
4. Verifying unassigned subjects appear correctly in the UI
