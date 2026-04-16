<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
startSession();
if (isLoggedIn()) redirect(BASE_URL . '/user/home.php');

$errors = []; $success = '';

// TU BSc CSIT Subjects per semester (for display)
$csitSems = [
    1 => 'Intro to IT, Digital Logic, C Programming, Maths I, Physics',
    2 => 'C++ OOP, Numerical Methods, Statistics, Discrete Maths, Digital Electronics',
    3 => 'Data Structures, Algorithms, DBMS, OOP Java, Operating Systems',
    4 => 'Computer Graphics, Networks, Software Eng, Microprocessor, AI',
    5 => 'DotNet, Simulation, Web Tech, Theory of Computation, Compiler',
    6 => 'Advanced Java, Distributed Systems, Mobile Computing, Networking',
    7 => 'Cloud Computing, Cyber Security, Machine Learning, Electives',
    8 => 'Project Work, Research Methodology, Seminar, Electives',
];
// TU BCA Subjects per semester
$bcaSems = [
    1 => 'Computer Fundamentals, Society & Technology, English I, Maths I, Digital Logic',
    2 => 'C Programming, Financial Accounting, English II, Maths II, Microprocessor',
    3 => 'Data Structures, Probability & Statistics, System Analysis, OOP Java, Web Tech',
    4 => 'Operating System, Numerical Methods, Software Eng, Scripting Language, DBMS',
    5 => 'MIS & E-Business, DotNet, Computer Networking, Management, Computer Graphics',
    6 => 'Mobile Programming, Distributed System, Applied Economics, Advanced Java, Network Prog',
    7 => 'Cyber Law & Ethics, Cloud Computing, Internship, Elective',
    8 => 'Project Work, Research Methods, Seminar, Elective',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = sanitize($_POST['name']    ?? '');
    $email    = sanitize($_POST['email']   ?? '');
    $phone    = sanitize($_POST['phone']   ?? '');
    $course   = sanitize($_POST['course']  ?? '');
    $semester = intval($_POST['semester']  ?? 0);
    $password = $_POST['password']          ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Name validation
    if (empty($name)) {
        $errors['name'] = 'Full name is required.';
    } elseif (strlen($name) < 3 || strlen($name) > 100) {
        $errors['name'] = 'Name must be 3-100 characters.';
    } elseif (!preg_match('/[a-zA-Z]/', $name)) {
        $errors['name'] = 'Name must contain letters, not just numbers.';
    } elseif (preg_match('/^[0-9\s]+$/', $name)) {
        $errors['name'] = 'Name cannot contain only numbers.';
    } elseif (!preg_match('/^[a-zA-Z\s\.\-]+$/', $name)) {
        $errors['name'] = 'Name can only contain letters, spaces, dots and hyphens.';
    }

    // Email
    if (empty($email)) {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    // Course
    if (!in_array($course, ['BSc CSIT', 'BCA'])) {
        $errors['course'] = 'Please select a valid course.';
    }

    // Semester
    if ($semester < 1 || $semester > 8) {
        $errors['semester'] = 'Please select your current semester (1-8).';
    }

    // Phone (optional)
    if (!empty($phone) && !preg_match('/^[+]?[0-9\s\-]{10,15}$/', $phone)) {
        $errors['phone'] = 'Enter a valid phone number.';
    }

    // Password
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors['password'] = 'Password needs at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors['password'] = 'Password needs at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password needs at least one number.';
    }

    if ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $conn = getDBConnection();
        $chk = mysqli_prepare($conn, "SELECT id FROM users WHERE email=?");
        mysqli_stmt_bind_param($chk, 's', $email);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        if (mysqli_stmt_num_rows($chk) > 0) {
            $errors['email'] = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = mysqli_prepare($conn,
                "INSERT INTO users (name,email,password,phone,role,course,semester)
                 VALUES (?,?,?,?,'student',?,?)");
            mysqli_stmt_bind_param($ins, 'sssssi',
                $name, $email, $hash, $phone, $course, $semester);
            if (mysqli_stmt_execute($ins)) {
                $success = "Account created! You can now log in.";
            } else {
                $errors['general'] = 'Registration failed. Please try again.';
            }
        }
        mysqli_close($conn);
    }
}

$selCourse = sanitize($_POST['course'] ?? '');
$selSem    = intval($_POST['semester'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register | Gyansetu — <?php echo COLLEGE_NAME; ?></title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{width:100%;min-height:100%;}
body{font-family:var(--font-body);
     background:linear-gradient(135deg,#1a0808,#4A1C1C);
     min-height:100vh;display:flex;align-items:center;
     justify-content:center;padding:2rem;}
.card{background:#fff;border-radius:18px;
      box-shadow:0 20px 60px rgba(0,0,0,.4);
      padding:2.25rem;width:100%;max-width:580px;}
.card-header{text-align:center;margin-bottom:1.5rem;}
.card-header .logo{font-size:2.5rem;display:block;margin-bottom:.4rem;}
.card-header h1{font-family:var(--font-heading);font-size:1.7rem;color:#4A1C1C;}
.card-header p{color:#aaa;font-size:.85rem;margin-top:.2rem;}
.success-box{background:#d4edda;border-left:4px solid #28a745;border-radius:6px;
    padding:.75rem 1rem;margin-bottom:1rem;color:#155724;font-size:.9rem;}
.err-box{background:#fdf0f0;border-left:4px solid #e74c3c;border-radius:6px;
    padding:.72rem 1rem;margin-bottom:1rem;color:#c0392b;font-size:.86rem;}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:.85rem;}
.fld{margin-bottom:.9rem;}
.fld label{display:block;font-size:.7rem;font-weight:700;color:#777;
    text-transform:uppercase;letter-spacing:.08em;margin-bottom:.35rem;}
.ir{position:relative;}
.ir .ic{position:absolute;left:.85rem;top:50%;transform:translateY(-50%);
    color:#ccc;font-size:.9rem;pointer-events:none;}
.ir input,.fld select{
    width:100%;padding:.72rem 1rem .72rem 2.5rem;
    border:2px solid #e8e0d5;border-radius:8px;
    font-size:.9rem;font-family:var(--font-body);
    color:#1a0808;background:#faf6ef;outline:none;
    transition:border-color .2s,box-shadow .2s,background .2s;}
.fld select{padding:.72rem 1rem;}
.ir input:focus,.fld select:focus{
    border-color:#4A1C1C;background:#fff;
    box-shadow:0 0 0 3px rgba(74,28,28,.08);}
.is-invalid{border-color:#e74c3c!important;}
.ferr{color:#e74c3c;font-size:.75rem;margin-top:.25rem;}
.eyebtn{position:absolute;right:.8rem;top:50%;transform:translateY(-50%);
    background:none;border:none;cursor:pointer;color:#bbb;font-size:.9rem;}
.eyebtn:hover{color:#4A1C1C;}
/* Password strength */
.str-bar{height:4px;border-radius:2px;margin-top:.35rem;background:#e8e0d5;overflow:hidden;}
.str-fill{height:100%;border-radius:2px;transition:all .3s;width:0;}
.str-txt{font-size:.7rem;margin-top:.2rem;}
/* Semester hint */
.sem-hint{font-size:.72rem;color:#888;background:#faf6ef;
    border:1px solid #e8e0d5;border-radius:6px;
    padding:.5rem .75rem;margin-top:.4rem;
    min-height:2.5rem;transition:all .3s;}
.sbtn{width:100%;padding:.85rem;background:#4A1C1C;color:#fff;border:none;
    border-radius:8px;font-size:.95rem;font-weight:700;
    font-family:var(--font-body);cursor:pointer;margin-top:.4rem;
    transition:background .2s,transform .15s,box-shadow .2s;}
.sbtn:hover{background:#6B2D2D;transform:translateY(-1px);
    box-shadow:0 5px 18px rgba(74,28,28,.28);}
.login-link{text-align:center;margin-top:1rem;font-size:.86rem;color:#aaa;}
.login-link a{color:#4A1C1C;font-weight:700;text-decoration:none;}
@media(max-width:520px){.frow{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <span class="logo">&#128218;</span>
        <h1>Create Account</h1>
        <p><?php echo COLLEGE_NAME; ?> — Library System</p>
    </div>

    <?php if ($success): ?>
    <div class="success-box">&#10003; <?php echo $success; ?>
        <a href="<?php echo BASE_URL; ?>/login.php" style="font-weight:700;margin-left:.5rem;">
            Login now &#8594;</a>
    </div>
    <?php endif; ?>
    <?php if (!empty($errors['general'])): ?>
    <div class="err-box">&#10007; <?php echo $errors['general']; ?></div>
    <?php endif; ?>

    <form method="POST" id="regForm">

        <div class="frow">
            <!-- Name -->
            <div class="fld">
                <label>Full Name *</label>
                <div class="ir">
                    <span class="ic">&#128100;</span>
                    <input type="text" name="name" id="fname"
                           class="<?php echo isset($errors['name'])?'is-invalid':''; ?>"
                           placeholder="Ram Sharma"
                           value="<?php echo sanitize($_POST['name']??''); ?>"
                           required maxlength="100">
                </div>
                <?php if (isset($errors['name'])): ?>
                <div class="ferr">&#10007; <?php echo $errors['name']; ?></div>
                <?php endif; ?>
            </div>

            <!-- Email -->
            <div class="fld">
                <label>Email Address *</label>
                <div class="ir">
                    <span class="ic">&#9993;</span>
                    <input type="email" name="email"
                           class="<?php echo isset($errors['email'])?'is-invalid':''; ?>"
                           placeholder="you@email.com"
                           value="<?php echo sanitize($_POST['email']??''); ?>"
                           required maxlength="150">
                </div>
                <?php if (isset($errors['email'])): ?>
                <div class="ferr">&#10007; <?php echo $errors['email']; ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="frow">
            <!-- Course -->
            <div class="fld">
                <label>Course *</label>
                <select name="course" id="selCourse"
                        class="<?php echo isset($errors['course'])?'is-invalid':''; ?>"
                        onchange="updateSemesters()"
                        required>
                    <option value="">— Select Course —</option>
                    <option value="BSc CSIT" <?php echo $selCourse==='BSc CSIT'?'selected':''; ?>>
                        BSc CSIT (TU)
                    </option>
                    <option value="BCA" <?php echo $selCourse==='BCA'?'selected':''; ?>>
                        BCA (TU)
                    </option>
                </select>
                <?php if (isset($errors['course'])): ?>
                <div class="ferr">&#10007; <?php echo $errors['course']; ?></div>
                <?php endif; ?>
            </div>

            <!-- Semester -->
            <div class="fld">
                <label>Current Semester *</label>
                <select name="semester" id="selSem"
                        class="<?php echo isset($errors['semester'])?'is-invalid':''; ?>"
                        onchange="showSemHint()"
                        required>
                    <option value="">— Select Semester —</option>
                    <?php for ($s=1; $s<=8; $s++): ?>
                    <option value="<?php echo $s; ?>"
                            <?php echo $selSem===$s?'selected':''; ?>>
                        Semester <?php echo $s; ?>
                    </option>
                    <?php endfor; ?>
                </select>
                <?php if (isset($errors['semester'])): ?>
                <div class="ferr">&#10007; <?php echo $errors['semester']; ?></div>
                <?php endif; ?>
                <!-- Dynamic subject hint -->
                <div class="sem-hint" id="semHint">
                    Select course and semester to see subjects
                </div>
            </div>
        </div>

        <!-- Phone -->
        <div class="fld">
            <label>Phone (Optional)</label>
            <div class="ir">
                <span class="ic">&#128222;</span>
                <input type="tel" name="phone"
                       class="<?php echo isset($errors['phone'])?'is-invalid':''; ?>"
                       placeholder="+977-9800000000"
                       value="<?php echo sanitize($_POST['phone']??''); ?>"
                       maxlength="15">
            </div>
            <?php if (isset($errors['phone'])): ?>
            <div class="ferr">&#10007; <?php echo $errors['phone']; ?></div>
            <?php endif; ?>
        </div>

        <div class="frow">
            <!-- Password -->
            <div class="fld">
                <label>Password *</label>
                <div class="ir">
                    <span class="ic">&#128274;</span>
                    <input type="password" name="password" id="pw"
                           class="<?php echo isset($errors['password'])?'is-invalid':''; ?>"
                           placeholder="Min 8 chars" required maxlength="50"
                           oninput="checkStr(this.value)">
                    <button type="button" class="eyebtn"
                        onclick="var i=document.getElementById('pw');
                                 this.innerHTML=i.type==='password'?(i.type='text','&#128064;'):(i.type='password','&#128065;')">
                        &#128065;</button>
                </div>
                <div class="str-bar"><div class="str-fill" id="sf"></div></div>
                <div class="str-txt" id="st"></div>
                <?php if (isset($errors['password'])): ?>
                <div class="ferr">&#10007; <?php echo $errors['password']; ?></div>
                <?php else: ?>
                <div style="font-size:.7rem;color:#aaa;margin-top:.2rem;">
                    Uppercase + lowercase + number required
                </div>
                <?php endif; ?>
            </div>

            <!-- Confirm -->
            <div class="fld">
                <label>Confirm Password *</label>
                <div class="ir">
                    <span class="ic">&#128274;</span>
                    <input type="password" name="confirm_password" id="pc"
                           class="<?php echo isset($errors['confirm_password'])?'is-invalid':''; ?>"
                           placeholder="Repeat password" required maxlength="50">
                    <button type="button" class="eyebtn"
                        onclick="var i=document.getElementById('pc');
                                 this.innerHTML=i.type==='password'?(i.type='text','&#128064;'):(i.type='password','&#128065;')">
                        &#128065;</button>
                </div>
                <?php if (isset($errors['confirm_password'])): ?>
                <div class="ferr">&#10007; <?php echo $errors['confirm_password']; ?></div>
                <?php endif; ?>
            </div>
        </div>

        <button type="submit" class="sbtn">Create Library Account &#8594;</button>
    </form>

    <p class="login-link">
        Already have an account?
        <a href="<?php echo BASE_URL; ?>/login.php">Sign in</a>
    </p>
</div>

<script>
// Semester subjects data
var csitSems = {
    1: 'Intro to IT, Digital Logic, C Programming, Maths I, Physics',
    2: 'C++ OOP, Numerical Methods, Statistics, Discrete Maths, Digital Electronics',
    3: 'Data Structures, Algorithms, DBMS, OOP Java, Operating Systems',
    4: 'Computer Graphics, Networks, Software Eng, Microprocessor, AI',
    5: 'DotNet Technology, Simulation, Web Tech, Theory of Computation, Compiler Design',
    6: 'Advanced Java, Distributed Systems, Mobile Computing, Network Programming',
    7: 'Cloud Computing, Cyber Security, Machine Learning, Electives',
    8: 'Project Work, Research Methodology, Seminar, Electives'
};
var bcaSems = {
    1: 'Computer Fundamentals, Society & Technology, English I, Maths I, Digital Logic',
    2: 'C Programming, Financial Accounting, English II, Maths II, Microprocessor',
    3: 'Data Structures, Probability & Stats, System Analysis, OOP Java, Web Tech',
    4: 'Operating System, Numerical Methods, Software Eng, Scripting Language, DBMS, Project I',
    5: 'MIS & E-Business, DotNet, Computer Networking, Management, Computer Graphics',
    6: 'Mobile Programming, Distributed System, Applied Economics, Advanced Java, Network Prog, Project II',
    7: 'Cyber Law & Ethics, Cloud Computing, Internship, Elective',
    8: 'Project Work, Research Methods, Seminar, Elective'
};

function showSemHint() {
    var course = document.getElementById('selCourse').value;
    var sem    = parseInt(document.getElementById('selSem').value);
    var hint   = document.getElementById('semHint');
    if (course && sem) {
        var data = course === 'BCA' ? bcaSems : csitSems;
        hint.innerHTML = '<strong>Sem ' + sem + ' Subjects:</strong> ' + (data[sem] || '');
        hint.style.background = '#f0f7ff';
        hint.style.borderColor = '#4A1C1C';
    }
}

function updateSemesters() { showSemHint(); }

// Password strength
function checkStr(v) {
    var sf = document.getElementById('sf');
    var st = document.getElementById('st');
    var s  = 0;
    if (v.length >= 8)          s++;
    if (/[A-Z]/.test(v))        s++;
    if (/[a-z]/.test(v))        s++;
    if (/[0-9]/.test(v))        s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    var c=['','#e74c3c','#e67e22','#f0c040','#2ecc71','#27ae60'];
    var l=['','Very Weak','Weak','Fair','Strong','Very Strong'];
    sf.style.width=(s*20)+'%'; sf.style.background=c[s]||'#e8e0d5';
    st.textContent=v.length>0?(l[s]||''):''; st.style.color=c[s]||'#aaa';
}

// Client validation
document.getElementById('regForm').addEventListener('submit', function(e) {
    var errs = [];
    var name = document.getElementById('fname').value.trim();
    var pw   = document.getElementById('pw').value;
    var pc   = document.getElementById('pc').value;

    if (!name || !name.match(/[a-zA-Z]/) || /^[0-9\s]+$/.test(name)) {
        errs.push('Name must contain letters.');
    }
    if (pw.length < 8)          errs.push('Password min 8 chars.');
    if (!/[A-Z]/.test(pw))      errs.push('Password needs uppercase.');
    if (!/[a-z]/.test(pw))      errs.push('Password needs lowercase.');
    if (!/[0-9]/.test(pw))      errs.push('Password needs a number.');
    if (pw !== pc)               errs.push('Passwords do not match.');

    if (errs.length) { e.preventDefault(); alert('Fix:\n\n' + errs.join('\n')); }
});

// Init hint if values already selected (after form error)
<?php if ($selCourse && $selSem): ?>
showSemHint();
<?php endif; ?>
</script>
</body>
</html>