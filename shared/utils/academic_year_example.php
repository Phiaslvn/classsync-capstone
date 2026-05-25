<?php
/**
 * Example Usage: Academic Year and Semester Auto-fill
 * This file demonstrates how to use the academic year calculator
 */

require_once __DIR__ . '/academic_year_calculator.php';

// Get current Academic Year and Semester
$academicPeriod = getCurrentAcademicPeriod();
$currentAcademicYear = $academicPeriod['academic_year'];
$currentSemester = $academicPeriod['semester'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Year Auto-fill Example</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 2rem 0;
        }
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #0066cc;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2 class="mb-4">
                <i class="bi bi-calendar3 me-2"></i>Academic Year & Semester Form
            </h2>
            
            <!-- Info Box -->
            <div class="info-box">
                <strong><i class="bi bi-info-circle me-2"></i>Auto-fill Information:</strong>
                <p class="mb-0 mt-2">
                    The form will automatically populate with:<br>
                    <strong>Academic Year:</strong> <?= htmlspecialchars($currentAcademicYear) ?><br>
                    <strong>Semester:</strong> <?= htmlspecialchars($currentSemester) ?>
                </p>
                <small class="text-muted">
                    Rules: 1st Semester (Aug–Dec), 2nd Semester (Jan–May), Mid-Year (Jun–Jul)
                </small>
            </div>
            
            <form id="academicForm" method="POST" action="#">
                <div class="mb-3">
                    <label for="academic_year" class="form-label">
                        <i class="bi bi-calendar-range me-1"></i>Academic Year <span class="text-danger">*</span>
                    </label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="academic_year" 
                        name="academic_year" 
                        placeholder="YYYY–YYYY (e.g., 2025–2026)"
                        required
                        pattern="\d{4}–\d{4}"
                        title="Format: YYYY–YYYY (e.g., 2025–2026)"
                    >
                    <div class="form-text">
                        Format: YYYY–YYYY (e.g., 2025–2026). Academic Year runs from August to July.
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="semester" class="form-label">
                        <i class="bi bi-calendar-week me-1"></i>Semester <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" id="semester" name="semester" required>
                        <option value="">-- Select Semester --</option>
                        <option value="1st">1st Semester (August–December)</option>
                        <option value="2nd">2nd Semester (January–May)</option>
                        <option value="Mid-Year">Mid-Year / Summer (June–July)</option>
                    </select>
                    <div class="form-text">
                        Select the current semester based on the month.
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Submit
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </button>
                    <button type="button" class="btn btn-outline-info" onclick="refillAcademicFields()">
                        <i class="bi bi-arrow-repeat me-1"></i>Re-fill
                    </button>
                </div>
            </form>
            
            <!-- Display Current Values (PHP) -->
            <div class="mt-4 p-3 bg-light rounded">
                <h6 class="mb-2">Current Values (PHP):</h6>
                <p class="mb-1"><strong>Academic Year:</strong> <?= htmlspecialchars($currentAcademicYear) ?></p>
                <p class="mb-0"><strong>Semester:</strong> <?= htmlspecialchars($currentSemester) ?></p>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Academic Year Auto-fill Script -->
    <script src="../assets/js/academic_year_autofill.js"></script>
    
    <script>
        // Initialize auto-fill on page load
        // The script will automatically detect fields with IDs 'academic_year' and 'semester'
        // and fill them on page load
        
        // Alternative: Manual initialization with custom options
        // window.AcademicYearAutoFill.init({
        //     academicYearFieldId: 'academic_year',
        //     semesterFieldId: 'semester',
        //     focusField: true,
        //     autoFillOnLoad: true
        // });
        
        // Function to reset form
        function resetForm() {
            document.getElementById('academicForm').reset();
            // Re-fill after reset
            setTimeout(function() {
                window.AcademicYearAutoFill.autoFill('academic_year', 'semester', true);
            }, 100);
        }
        
        // Function to manually re-fill fields
        function refillAcademicFields() {
            window.AcademicYearAutoFill.autoFill('academic_year', 'semester', true);
        }
        
        // Form submission handler
        document.getElementById('academicForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const academicYear = document.getElementById('academic_year').value;
            const semester = document.getElementById('semester').value;
            
            alert('Form submitted!\n\nAcademic Year: ' + academicYear + '\nSemester: ' + semester);
            
            // In a real application, you would submit to a PHP handler here
            // Example: this.action = 'process_form.php';
        });
        
        // Log current values for debugging
        console.log('Current Academic Year (JS):', window.AcademicYearAutoFill.calculateCurrentAcademicYear());
        console.log('Current Semester (JS):', window.AcademicYearAutoFill.calculateCurrentSemester());
    </script>
</body>
</html>

