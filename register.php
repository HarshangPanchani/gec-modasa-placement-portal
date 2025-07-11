<?php
// register.php
session_start();

// If user is already logged in, redirect them away from registration
if (isset($_SESSION['enrollment_no'])) {
    header("Location: profile.php");
    exit();
}

$errorMessage = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'db_connect.php';

    // ROBUST UPLOAD FUNCTION TO PREVENT ERRORS
    function handleUpload($file_input_name, $upload_subdir, $allowed_types, $enrollment_no, $file_purpose,$branch,$passout_year) {
        if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == 0) {
            $uploadDir = 'uploads/';
            $targetDir = $uploadDir . $upload_subdir . '/'. $branch . '/' . $passout_year . '/' ; 
            if (!is_dir($targetDir)) {
                // The `true` parameter creates nested directories if needed. 0755 are standard permissions.
                mkdir($targetDir, 0755, true);
            }
            $file = $_FILES[$file_input_name];
            
            // Securely get file extension
            $file_info = pathinfo($file['name']);
            if (!isset($file_info['extension'])) {
                return ['error' => "Invalid file for $file_purpose. The file must have an extension (e.g., .jpg, .pdf)."];
            }
            $fileExtension = strtolower($file_info['extension']);

            // Check if the file type is allowed
            if (!in_array($fileExtension, $allowed_types)) {
                return ['error' => "Invalid file type for $file_purpose. Must be one of: " . implode(', ', $allowed_types)];
            }
            
            $newFileName = $enrollment_no . '_' . $file_purpose . '.' . $fileExtension;
            $targetPath = $targetDir . $newFileName;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                return ['path' => $targetPath];
            } else {
                // This error will now likely only appear if permissions are still wrong.
                return ['error' => "Server error: Could not save the uploaded $file_purpose file. Please check folder permissions."];
            }
        }
        return ['path' => null]; // No file uploaded or optional
    }

    $enrollment_no = $_POST['enrollment_no'];
    $branch_f = $_POST['branch'];
    $passout_year_f = $_POST['passout_year'];
    $photoResult = handleUpload('photo', 'photos', ['jpg', 'jpeg', 'png', 'gif'], $enrollment_no, 'photo',$branch_f,$passout_year_f);
    $resumeResult = handleUpload('resume', 'resumes', ['pdf'], $enrollment_no, 'resume',$branch_f,$passout_year_f);
    
    if (isset($photoResult['error'])) $errorMessage = $photoResult['error'];
    if (isset($resumeResult['error'])) $errorMessage .= (empty($errorMessage) ? '' : '<br>') . $resumeResult['error'];

    if (empty($errorMessage)) {
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO students (email, enrollment_no, password, name, gender, category, city, whatsapp_no, secondary_no, photo_path, resume_path, ssc_percentage, hsc_diploma_percentage, current_cgpa, total_backlog, linkedin_url, github_url, portfolio_url, branch, admission_year, passout_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        // Safely assign all POST data to variables
        $email = $_POST['email'] ?? '';
        $name = $_POST['name'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $category = $_POST['category'] ?? '';
        $city = $_POST['city'] ?? '';
        $whatsapp_no = $_POST['whatsapp_no'] ?? '';
        $total_backlog = $_POST['total_backlog'] ?? 0;
        $branch = $_POST['branch'] ?? '';
        $admission_year = $_POST['admission_year'] ?? '';
        $passout_year = $_POST['passout_year'] ?? '';
        
        // Handle optional fields that can be NULL
        $secondary_no = !empty($_POST['secondary_no']) ? $_POST['secondary_no'] : null;
        $ssc_percentage = !empty($_POST['ssc_percentage']) ? $_POST['ssc_percentage'] : null;
        $hsc_diploma_percentage = !empty($_POST['hsc_diploma_percentage']) ? $_POST['hsc_diploma_percentage'] : null;
        $current_cgpa = !empty($_POST['current_cgpa']) ? $_POST['current_cgpa'] : null;
        $linkedin_url = !empty($_POST['linkedin_url']) ? $_POST['linkedin_url'] : null;
        $github_url = !empty($_POST['github_url']) ? $_POST['github_url'] : null;
        $portfolio_url = !empty($_POST['portfolio_url']) ? $_POST['portfolio_url'] : null;

        // The corrected $types string (21 characters) and bind_param call
        $stmt->bind_param("sssssssssssdddissssii", 
            $email, $enrollment_no, $hashedPassword, $name, $gender, $category, 
            $city, $whatsapp_no, $secondary_no, 
            $photoResult['path'], $resumeResult['path'], 
            $ssc_percentage, $hsc_diploma_percentage, $current_cgpa, 
            $total_backlog, $linkedin_url, $github_url, 
            $portfolio_url, $branch, $admission_year, $passout_year
        );
        
        if ($stmt->execute()) {
            $_SESSION['enrollment_no'] = $enrollment_no;
            header("Location: profile.php");
            exit();
        } else {
            if ($conn->errno == 1062) { 
                $errorMessage = "Error: A student with this Email or Enrollment Number already exists."; 
            } else { 
                $errorMessage = "Error creating record: " . $stmt->error; 
            }
        }
        $stmt->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="text-center mb-4">Student Registration</h2>

                    <?php if(!empty($errorMessage)): ?>
                        <div class="alert alert-danger" role="alert"><?php echo $errorMessage; ?></div>
                    <?php endif; ?>

                    <form action="register.php" method="post" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        
                        <div class="row">
                             <div class="col-md-6 mb-3">
                                <label for="enrollment_no" class="form-label">Enrollment No (12 digits) *</label>
                                <input type="text" class="form-control" id="enrollment_no" name="enrollment_no" pattern="\d{12}" title="Enrollment must be 12 digits" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-block">Gender *</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" id="male" name="gender" value="Male" required>
                                <label class="form-check-label" for="male">Male</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" id="female" name="gender" value="Female">
                                <label class="form-check-label" for="female">Female</label>
                            </div>
                             <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" id="other" name="gender" value="Other">
                                <label class="form-check-label" for="other">Other</label>
                            </div>
                        </div>

                        <div class="row">
                             <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Category *</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option selected disabled value="">Choose...</option>
                                    <option>OPEN</option><option>EWS</option><option>OBC</option><option>SC</option><option>ST</option><option>SEBC</option><option>OTHER</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">City *</label>
                                <input type="text" class="form-control" id="city" name="city" required>
                            </div>
                        </div>

                        <div class="row">
                             <div class="col-md-6 mb-3">
                                <label for="whatsapp_no" class="form-label">WhatsApp No *</label>
                                <input type="text" class="form-control" id="whatsapp_no" name="whatsapp_no" pattern="\d{10}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="secondary_no" class="form-label">Secondary No (Optional)</label>
                                <input type="text" class="form-control" id="secondary_no" name="secondary_no">
                            </div>
                        </div>
                        
                        <div class="row">
                             <div class="col-md-6 mb-3">
                                <label for="photo" class="form-label">Photo (JPG, PNG)</label>
                                <input class="form-control" type="file" id="photo" name="photo" accept="image/png, image/jpeg, image/gif">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="resume" class="form-label">Resume (PDF)</label>
                                <input class="form-control" type="file" id="resume" name="resume" accept=".pdf">
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="ssc_percentage" class="form-label">SSC Percentage</label>
                                <input type="number" class="form-control" id="ssc_percentage" name="ssc_percentage" step="0.01" min="0" max="100">
                            </div>
                             <div class="col-md-4 mb-3">
                                <label for="hsc_diploma_percentage" class="form-label">HSC/Diploma %</label>
                                <input type="number" class="form-control" id="hsc_diploma_percentage" name="hsc_diploma_percentage" step="0.01" min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="current_cgpa" class="form-label">Current CGPA</label>
                                <input type="number" class="form-control" id="current_cgpa" name="current_cgpa" step="0.01" min="0" max="10">
                            </div>
                        </div>

                         <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="branch" class="form-label">Branch *</label>
                                <select class="form-select" id="branch" name="branch" required>
                                    <option selected disabled value="">Choose...</option>
                                    <option>Computer Engineering</option><option>Information Technology</option><option>Mechanical Engineering</option><option>Civil Engineering</option><option>Electrical Engineering</option><option>Electronics & Communication Engineering</option><option>Automobile Engineering</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="admission_year" class="form-label">Admission Year *</label>
                                <input type="number" class="form-control" id="admission_year" name="admission_year" min="1980" max="<?php echo date('Y'); ?>" required>
                            </div>
                             <div class="col-md-4 mb-3">
                                <label for="passout_year" class="form-label">Expected Passout Year *</label>
                                <input type="number" class="form-control" id="passout_year" name="passout_year" min="<?php echo date('Y'); ?>" max="<?php echo date('Y')+5; ?>" required>
                            </div>
                        </div>
                        
                         <div class="mb-3">
                            <label for="total_backlog" class="form-label">Total Backlogs *</label>
                            <input type="number" class="form-control" id="total_backlog" value="0" min="0" name="total_backlog" required>
                        </div>
                        
                        <hr class="my-4">

                        <div class="mb-3">
                            <label for="linkedin_url" class="form-label">LinkedIn Profile URL</label>
                            <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" placeholder="https://www.linkedin.com/in/yourprofile">
                        </div>
                        <div class="mb-3">
                            <label for="github_url" class="form-label">GitHub Profile URL</label>
                            <input type="url" class="form-control" id="github_url" name="github_url" placeholder="https://github.com/yourusername">
                        </div>
                        <div class="mb-3">
                            <label for="portfolio_url" class="form-label">Personal Portfolio URL</label>
                            <input type="url" class="form-control" id="portfolio_url" name="portfolio_url">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 btn-lg">Register Student</button>
                        <p class="text-center mt-3">Already have an account? <a href="login.php">Login Here</a></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>