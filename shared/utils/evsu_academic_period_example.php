<?php
/**
 * EVSU Academic Period Example
 * Demonstrates how to use the EVSU Academic Year and Semester calculator
 */

require_once __DIR__ . '/evsu_academic_period.php';

// Get current Academic Period
$academicPeriod = getEVSUAcademicPeriod(); // Format: "2025 - 2026 | 1st Semester"
$academicPeriodArray = getEVSUAcademicPeriodArray();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVSU Academic Period Auto-fill</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 2rem 0;
        }
        .example-card {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .display-box {
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 1rem 0;
        }
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #0066cc;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }
        .form-control:focus {
            border-color: #800000;
            box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="example-card">
            <h2 class="mb-4">
                <i class="bi bi-calendar3 me-2"></i>EVSU Academic Period Auto-fill
            </h2>
            
            <!-- Info Box -->
            <div class="info-box">
                <strong><i class="bi bi-info-circle me-2"></i>EVSU Academic Calendar:</strong>
                <ul class="mb-0 mt-2">
                    <li><strong>1st Semester:</strong> August to December</li>
                    <li><strong>2nd Semester:</strong> January to May</li>
                    <li><strong>Mid-Year:</strong> June to July</li>
                </ul>
                <small class="text-muted">
                    Academic Year runs from August to July (e.g., Aug 2025 - Jul 2026 = 2025 - 2026)
                </small>
            </div>
            
            <!-- Display Current Academic Period (PHP) -->
            <div class="display-box">
                <div class="mb-2">
                    <i class="bi bi-calendar-range me-2"></i>Current Academic Period
                </div>
                <div id="php-display"><?= htmlspecialchars($academicPeriod) ?></div>
            </div>
            
            <!-- Example 1: Input Field -->
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Example 1: Input Field (Auto-filled)</h5>
                </div>
                <div class="card-body">
                    <label for="academic_period_input" class="form-label">
                        Academic Period <span class="text-danger">*</span>
                    </label>
                    <input 
                        type="text" 
                        class="form-control form-control-lg" 
                        id="academic_period_input" 
                        name="academic_period"
                        placeholder="Will auto-fill on page load..."
                        value="<?= htmlspecialchars($academicPeriod) ?>"
                        readonly
                    >
                    <div class="form-text">
                        <i class="bi bi-lightbulb me-1"></i>
                        This field is automatically filled and focused on page load. You can make it editable by removing the "readonly" attribute.
                    </div>
                </div>
            </div>
            
            <!-- Example 2: Editable Input Field -->
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Example 2: Editable Input Field</h5>
                </div>
                <div class="card-body">
                    <label for="academic_period_editable" class="form-label">
                        Academic Period (Editable) <span class="text-danger">*</span>
                    </label>
                    <input 
                        type="text" 
                        class="form-control form-control-lg" 
                        id="academic_period_editable" 
                        name="academic_period_editable"
                        placeholder="Will auto-fill, but you can edit..."
                    >
                    <div class="form-text">
                        <i class="bi bi-pencil me-1"></i>
                        This field auto-fills but allows manual editing.
                    </div>
                </div>
            </div>
            
            <!-- Example 3: Display Element -->
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Example 3: Display Element (Read-only)</h5>
                </div>
                <div class="card-body">
                    <label class="form-label">Current Academic Period:</label>
                    <div 
                        id="academic_period_display" 
                        class="form-control form-control-lg bg-light"
                        style="font-weight: 600; color: #800000;"
                    >
                        Loading...
                    </div>
                    <div class="form-text">
                        <i class="bi bi-eye me-1"></i>
                        This is a display-only element that shows the current academic period.
                    </div>
                </div>
            </div>
            
            <!-- Example 4: Form Integration -->
            <div class="card mb-3">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Example 4: Form Integration</h5>
                </div>
                <div class="card-body">
                    <form id="exampleForm">
                        <div class="mb-3">
                            <label for="form_academic_period" class="form-label">
                                Academic Period <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="form_academic_period" 
                                name="academic_period"
                                required
                            >
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>Submit Form
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="refreshAcademicPeriod()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Current Values Display -->
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Current Values (PHP & JavaScript)</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>PHP Values:</strong>
                            <ul class="mt-2">
                                <li><strong>Academic Year:</strong> <?= htmlspecialchars($academicPeriodArray['academic_year']) ?></li>
                                <li><strong>Semester:</strong> <?= htmlspecialchars($academicPeriodArray['semester']) ?></li>
                                <li><strong>Formatted:</strong> <?= htmlspecialchars($academicPeriodArray['formatted']) ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <strong>JavaScript Values:</strong>
                            <ul class="mt-2" id="js-values">
                                <li>Loading...</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- EVSU Academic Period Script -->
    <script src="../assets/js/evsu_academic_period.js"></script>
    
    <script>
        // Initialize auto-fill for different examples
        document.addEventListener('DOMContentLoaded', function() {
            // Example 1: Auto-fill input field (readonly, already filled by PHP)
            // Field is already filled, so we'll just focus it
            
            // Example 2: Auto-fill editable input field
            window.EVSUAcademicPeriod.init({
                inputFieldId: 'academic_period_editable',
                focusField: true,
                autoFillOnLoad: true
            });
            
            // Example 3: Auto-fill display element
            window.EVSUAcademicPeriod.init({
                displayElementId: 'academic_period_display',
                autoFillOnLoad: true
            });
            
            // Example 4: Auto-fill form field
            window.EVSUAcademicPeriod.init({
                inputFieldId: 'form_academic_period',
                focusField: true,
                autoFillOnLoad: true
            });
            
            // Display JavaScript values
            const jsValues = window.EVSUAcademicPeriod.getObject();
            document.getElementById('js-values').innerHTML = `
                <li><strong>Academic Year:</strong> ${jsValues.academicYear}</li>
                <li><strong>Semester:</strong> ${jsValues.semester}</li>
                <li><strong>Formatted:</strong> ${jsValues.formatted}</li>
            `;
            
            console.log('EVSU Academic Period:', jsValues);
        });
        
        // Function to refresh academic period
        function refreshAcademicPeriod() {
            const period = window.EVSUAcademicPeriod.getFormatted();
            
            // Update all fields
            document.getElementById('academic_period_editable').value = period;
            document.getElementById('academic_period_display').textContent = period;
            document.getElementById('form_academic_period').value = period;
            
            // Show alert
            alert('Academic Period refreshed:\n' + period);
        }
        
        // Form submission handler
        document.getElementById('exampleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const period = document.getElementById('form_academic_period').value;
            alert('Form submitted!\n\nAcademic Period: ' + period);
        });
    </script>
</body>
</html>

