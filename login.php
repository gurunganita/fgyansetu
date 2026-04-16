<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
startSession();

if (isLoggedIn()) {
    redirect(isAdmin() ? BASE_URL.'/admin/dashboard.php' : BASE_URL.'/user/home.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email']   ?? '');
    $password = $_POST['password']          ?? '';
    $faculty  = sanitize($_POST['faculty'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (empty($faculty)) {
        $error = 'Please select your faculty.';
    } else {
        $conn = getDBConnection();
        $stmt = mysqli_prepare($conn,
            "SELECT id,name,email,password,role,course,semester
             FROM users WHERE email=? AND role='student'");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($row && password_verify($password, $row['password'])) {
            if ($row['course'] !== $faculty) {
                $error = 'Wrong faculty selected. Your account is registered under ' . $row['course'] . '.';
            } else {
                $_SESSION['user_id']    = $row['id'];
                $_SESSION['user_name']  = $row['name'];
                $_SESSION['user_email'] = $row['email'];
                $_SESSION['role']       = $row['role'];
                $_SESSION['course']     = $row['course'];
                $_SESSION['semester']   = $row['semester'] ?? 1;
                mysqli_close($conn);
                redirect(BASE_URL . '/user/home.php');
            }
        } else {
            $error = 'Invalid email or password.';
        }
        if (isset($conn)) mysqli_close($conn);
    }
}
$selFac = sanitize($_POST['faculty'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Student Login — Gyansetu | <?php echo COLLEGE_NAME; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
    --red:#4A1C1C; --red-dk:#2C0F0F; --red-lt:#6B2D2D;
    --gold:#C9973C; --gold-lt:#E8B96B;
    --ivory:#F8F3EC; --ivory-dk:#EDE5D5;
    --text:#1a0808; --muted:#888;
    --border:#E2D8CC;
    --ff-head:'Playfair Display',Georgia,serif;
    --ff-body:'DM Sans',system-ui,sans-serif;
}
html,body{height:100%;font-family:var(--ff-body);background:var(--ivory);}
body{display:flex;min-height:100vh;}

/* ─── LEFT PANEL ─── */
.lp{
    width:420px;flex-shrink:0;
    background:linear-gradient(170deg,var(--red-dk) 0%,var(--red) 50%,var(--red-lt) 100%);
    display:flex;flex-direction:column;justify-content:space-between;
    padding:3rem 2.5rem;position:relative;overflow:hidden;
}
.lp-circles{position:absolute;inset:0;pointer-events:none;}
.lp-circle{position:absolute;border-radius:50%;border:1px solid rgba(255,255,255,.08);}
.lp-c1{width:400px;height:400px;top:-100px;right:-150px;}
.lp-c2{width:280px;height:280px;bottom:-60px;left:-80px;}
.lp-c3{width:180px;height:180px;top:40%;left:30%;}
.lp-logo{display:flex;align-items:center;gap:.75rem;margin-bottom:2.5rem;}
.lp-logo-icon{
    width:48px;height:48px;
    background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.2);
    border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    font-size:1.4rem;
}
.lp-logo-text{color:#fff;}
.lp-logo-text strong{font-family:var(--ff-head);font-size:1.1rem;display:block;}
.lp-logo-text span{font-size:.72rem;color:var(--gold-lt);letter-spacing:.08em;text-transform:uppercase;}

.lp-heading{font-family:var(--ff-head);font-size:2.1rem;color:#fff;line-height:1.2;margin-bottom:.75rem;}
.lp-heading em{color:var(--gold-lt);font-style:normal;}
.lp-sub{color:rgba(255,255,255,.5);font-size:.88rem;line-height:1.6;margin-bottom:2rem;}

/* Faculty info cards */
.fac-info{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:2rem;}
.fi-card{
    background:rgba(255,255,255,.07);
    border:1px solid rgba(255,255,255,.12);
    border-radius:12px;padding:1rem .85rem;
    transition:all .2s;
}
.fi-card:hover{background:rgba(255,255,255,.1);}
.fi-icon{font-size:1.4rem;display:block;margin-bottom:.4rem;}
.fi-name{font-weight:700;font-size:.88rem;color:#fff;}
.fi-detail{font-size:.72rem;color:rgba(255,255,255,.45);margin-top:.2rem;}

/* Features */
.lp-feat{display:flex;align-items:center;gap:.65rem;padding:.45rem 0;
    border-bottom:1px solid rgba(255,255,255,.06);}
.lp-feat:last-child{border:none;}
.lp-feat-dot{
    width:28px;height:28px;flex-shrink:0;border-radius:8px;
    background:rgba(201,151,60,.2);
    display:flex;align-items:center;justify-content:center;
    font-size:.8rem;
}
.lp-feat-text{font-size:.82rem;color:rgba(255,255,255,.65);}

/* ─── RIGHT PANEL ─── */
.rp{
    flex:1;display:flex;align-items:center;justify-content:center;
    padding:2rem;background:var(--ivory);
}
.form-card{
    width:100%;max-width:420px;
    background:#fff;
    border-radius:20px;
    box-shadow:0 8px 48px rgba(74,28,28,.1);
    border:1px solid var(--border);
    padding:2.25rem;
}
.fc-eyebrow{
    font-size:.7rem;font-weight:600;
    color:var(--red);text-transform:uppercase;
    letter-spacing:.12em;margin-bottom:.35rem;
    display:flex;align-items:center;gap:.4rem;
}
.fc-eyebrow::before{content:'';display:block;width:18px;height:2px;background:var(--red);}
.fc-title{font-family:var(--ff-head);font-size:1.8rem;color:var(--text);margin-bottom:.25rem;}
.fc-sub{color:var(--muted);font-size:.84rem;margin-bottom:1.5rem;}

/* Error box */
.err-box{
    background:#fdf0f0;border-left:3px solid #e74c3c;
    border-radius:8px;padding:.7rem 1rem;
    margin-bottom:1.1rem;color:#c0392b;
    font-size:.85rem;display:flex;align-items:center;gap:.5rem;
}

/* Faculty selector */
.fac-label{
    font-size:.7rem;font-weight:600;color:#666;
    text-transform:uppercase;letter-spacing:.08em;
    margin-bottom:.5rem;
}
.fac-grid{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:1.1rem;}
.fac-option{position:relative;}
.fac-option input[type=radio]{position:absolute;opacity:0;width:0;height:0;}
.fac-option label{
    display:flex;flex-direction:column;align-items:center;
    gap:.3rem;padding:.8rem .5rem;
    border:2px solid var(--border);
    border-radius:12px;cursor:pointer;
    background:var(--ivory);
    transition:all .2s;
    text-align:center;
}
.fac-option label .fo-icon{font-size:1.3rem;}
.fac-option label .fo-name{font-size:.88rem;font-weight:700;color:#555;}
.fac-option label .fo-years{font-size:.68rem;color:var(--muted);}
.fac-option input:checked + label{
    border-color:var(--red);
    background:#fff;
    box-shadow:0 0 0 3px rgba(74,28,28,.08);
}
.fac-option input:checked + label .fo-name{color:var(--red);}
.fac-option label:hover{border-color:#8B4444;background:#fff;}
.fac-err{color:#e74c3c;font-size:.75rem;margin-bottom:.75rem;display:none;}

/* Fields */
.fld{margin-bottom:.9rem;}
.fld-label{
    display:block;font-size:.7rem;font-weight:600;
    color:#666;text-transform:uppercase;
    letter-spacing:.08em;margin-bottom:.4rem;
}
.ir{position:relative;}
.ir-icon{
    position:absolute;left:.9rem;top:50%;transform:translateY(-50%);
    color:#bbb;font-size:.95rem;pointer-events:none;
}
.ir input{
    width:100%;padding:.75rem 1rem .75rem 2.6rem;
    border:1.5px solid var(--border);border-radius:10px;
    font-size:.93rem;font-family:var(--ff-body);
    color:var(--text);background:var(--ivory);outline:none;
    transition:border-color .2s,box-shadow .2s,background .2s;
}
.ir input:focus{
    border-color:var(--red);background:#fff;
    box-shadow:0 0 0 3px rgba(74,28,28,.07);
}
.eyebtn{
    position:absolute;right:.8rem;top:50%;transform:translateY(-50%);
    background:none;border:none;cursor:pointer;
    color:#bbb;font-size:.9rem;padding:.2rem;
    transition:color .2s;
}
.eyebtn:hover{color:var(--red);}

/* Submit button */
.sbtn{
    width:100%;padding:.85rem;
    background:var(--red);color:#fff;border:none;
    border-radius:10px;font-size:.95rem;font-weight:600;
    font-family:var(--ff-body);cursor:pointer;
    letter-spacing:.03em;margin-top:.25rem;
    transition:background .2s,transform .15s,box-shadow .2s;
    display:flex;align-items:center;justify-content:center;gap:.5rem;
}
.sbtn:hover{
    background:var(--red-lt);transform:translateY(-1px);
    box-shadow:0 5px 20px rgba(74,28,28,.25);
}

/* Divider */
.divdr{display:flex;align-items:center;gap:.75rem;margin:1rem 0;color:#ddd;font-size:.78rem;}
.divdr::before,.divdr::after{content:'';flex:1;height:1px;background:var(--border);}

/* Admin link */
.admin-link{
    display:flex;align-items:center;justify-content:center;gap:.5rem;
    width:100%;padding:.7rem;
    background:#fff;border:1.5px solid var(--border);border-radius:10px;
    color:#666;font-size:.86rem;font-weight:500;
    font-family:var(--ff-body);text-decoration:none;
    transition:all .2s;
}
.admin-link:hover{border-color:var(--red);color:var(--red);background:var(--ivory);}

.reg-link{text-align:center;margin-top:1rem;font-size:.84rem;color:var(--muted);}
.reg-link a{color:var(--red);font-weight:600;text-decoration:none;}

@media(max-width:820px){
    .lp{display:none;}
    .rp{background:linear-gradient(170deg,var(--red-dk),var(--red-lt));}
    .form-card{box-shadow:0 20px 60px rgba(0,0,0,.3);}
}
</style>
</head>
<body>

<div class="lp">
    <div class="lp-circles">
        <div class="lp-circle lp-c1"></div>
        <div class="lp-circle lp-c2"></div>
        <div class="lp-circle lp-c3"></div>
    </div>

    <div class="lp-top">
        <div class="lp-logo">
            <div class="lp-logo-icon">&#128218;</div>
            <div class="lp-logo-text">
                <strong>Gyansetu</strong>
                <span><?php echo COLLEGE_NAME; ?></span>
            </div>
        </div>

        <h1 class="lp-heading">Your <em>Library,</em><br>Reimagined.</h1>
        <p class="lp-sub">Access semester-wise books, track borrows, get AI-powered recommendations — all in one place.</p>

        <div class="fac-info">
            <div class="fi-card">
                <span class="fi-icon">&#128187;</span>
                <div class="fi-name">BSc CSIT</div>
                <div class="fi-detail">4 Years · 8 Semesters</div>
            </div>
            <div class="fi-card">
                <span class="fi-icon">&#128200;</span>
                <div class="fi-name">BCA</div>
                <div class="fi-detail">4 Years · 8 Semesters</div>
            </div>
        </div>

        <div class="lp-features">
            <div class="lp-feat"><div class="lp-feat-dot">&#128218;</div><span class="lp-feat-text">Semester-wise book catalog</span></div>
            <div class="lp-feat"><div class="lp-feat-dot">&#11088;</div><span class="lp-feat-text">AI Hybrid Recommendations</span></div>
            <div class="lp-feat"><div class="lp-feat-dot">&#9203;</div><span class="lp-feat-text">FIFO borrow request system</span></div>
            <div class="lp-feat"><div class="lp-feat-dot">&#128276;</div><span class="lp-feat-text">Real-time notifications</span></div>
        </div>
    </div>

    <div style="color:rgba(255,255,255,.3);font-size:.75rem;">
        &copy; <?php echo date('Y'); ?> <?php echo COLLEGE_NAME; ?>
    </div>
</div>

<div class="rp">
    <div class="form-card">
        <div class="fc-eyebrow">Student Portal</div>
        <h1 class="fc-title">Welcome Back</h1>
        <p class="fc-sub"><?php echo COLLEGE_NAME; ?> Library</p>

        <?php if ($error): ?>
        <div class="err-box">&#10007; <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" id="lf">
            <div class="fac-label">Select Your Faculty *</div>
            <div class="fac-grid">
                <div class="fac-option">
                    <input type="radio" name="faculty" id="fc_csit" value="BSc CSIT"
                           <?php echo $selFac==='BSc CSIT'?'checked':''; ?>>
                    <label for="fc_csit">
                        <span class="fo-icon">&#128187;</span>
                        <span class="fo-name">BSc CSIT</span>
                        <span class="fo-years">TU · 4 Years</span>
                    </label>
                </div>
                <div class="fac-option">
                    <input type="radio" name="faculty" id="fc_bca" value="BCA"
                           <?php echo $selFac==='BCA'?'checked':''; ?>>
                    <label for="fc_bca">
                        <span class="fo-icon">&#128200;</span>
                        <span class="fo-name">BCA</span>
                        <span class="fo-years">TU · 4 Years</span>
                    </label>
                </div>
            </div>
            <div class="fac-err" id="facErr">Please select your faculty.</div>

            <div class="fld">
                <label class="fld-label">Email Address</label>
                <div class="ir">
                    <span class="ir-icon">&#9993;</span>
                    <input type="email" name="email" placeholder="your@email.com"
                           value="<?php echo sanitize($_POST['email']??''); ?>"
                           required autofocus>
                </div>
            </div>

            <div class="fld">
                <label class="fld-label">Password</label>
                <div class="ir">
                    <span class="ir-icon">&#128274;</span>
                    <input type="password" id="pw" name="password"
                           placeholder="Enter your password" required>
                    <button type="button" class="eyebtn" id="eye"
                        onclick="var i=document.getElementById('pw');
                                 this.textContent=i.type==='password'?(i.type='text','&#128064;'):(i.type='password','&#128065;')">
                        &#128065;</button>
                </div>
            </div>

            <button type="submit" class="sbtn">Sign In &#8594;</button>
        </form>

        <div class="divdr">or</div>
        <a href="<?php echo BASE_URL; ?>/admin/login.php" class="admin-link">
            &#128737; Administrator Login
        </a>
        <p class="reg-link">
            No account?
            <a href="<?php echo BASE_URL; ?>/register.php">Create one here</a>
        </p>
    </div>
</div>

<script>
document.getElementById('lf').addEventListener('submit',function(e){
    if (!document.querySelector('input[name="faculty"]:checked')) {
        document.getElementById('facErr').style.display='block';
        e.preventDefault();
    }
});
document.querySelectorAll('input[name="faculty"]').forEach(function(r){
    r.addEventListener('change',function(){
        document.getElementById('facErr').style.display='none';
    });
});
</script>
</body>
</html>