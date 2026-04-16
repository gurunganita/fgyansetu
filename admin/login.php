<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
startSession();

if (isLoggedIn() && isAdmin()) redirect(BASE_URL.'/admin/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email']    ?? '');
    $password = $_POST['password']           ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $conn = getDBConnection();
        $stmt = mysqli_prepare($conn,
            "SELECT id,name,email,password,role FROM users
             WHERE email=? AND role='admin'");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($row && password_verify($password, $row['password'])) {
            $_SESSION['user_id']    = $row['id'];
            $_SESSION['user_name']  = $row['name'];
            $_SESSION['user_email'] = $row['email'];
            $_SESSION['role']       = 'admin';
            $_SESSION['course']     = 'admin';
            mysqli_close($conn);
            redirect(BASE_URL . '/admin/dashboard.php');
        } else {
            $error = 'Invalid administrator credentials.';
        }
        if (isset($conn)) mysqli_close($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login — Gyansetu</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
    --bg:#0D0D0D; --card:#161616; --border:#2a2a2a;
    --red:#8B3030; --red-lt:#C04040; --gold:#C9973C;
    --text:#EDEDED; --muted:#666;
    --ff-head:'Playfair Display',Georgia,serif;
    --ff-body:'DM Sans',system-ui,sans-serif;
}
html,body{height:100%;font-family:var(--ff-body);}
body{
    background:var(--bg);
    display:flex;align-items:center;justify-content:center;
    min-height:100vh;padding:2rem;
    position:relative;overflow:hidden;
}

/* Background decoration */
body::before{
    content:'';position:fixed;inset:0;
    background:
        radial-gradient(ellipse 600px 400px at 20% 50%, rgba(139,48,48,.15) 0%, transparent 70%),
        radial-gradient(ellipse 400px 300px at 80% 20%, rgba(201,151,60,.08) 0%, transparent 60%);
    pointer-events:none;
}

.login-wrap{
    position:relative;z-index:1;
    width:100%;max-width:440px;
}

/* Logo area */
.logo-area{text-align:center;margin-bottom:2rem;}
.logo-icon-wrap{
    width:64px;height:64px;
    background:linear-gradient(135deg,var(--red),var(--red-lt));
    border-radius:18px;
    display:inline-flex;align-items:center;justify-content:center;
    font-size:1.8rem;
    box-shadow:0 8px 32px rgba(139,48,48,.4);
    margin-bottom:1rem;
}
.logo-area h1{
    font-family:var(--ff-head);
    font-size:1.6rem;color:var(--text);margin-bottom:.25rem;
}
.logo-area p{color:var(--muted);font-size:.85rem;}

/* Card */
.card{
    background:var(--card);
    border-radius:20px;
    border:1px solid var(--border);
    padding:2.25rem;
    box-shadow:0 24px 64px rgba(0,0,0,.6);
    position:relative;overflow:hidden;
}
.card::before{
    content:'';position:absolute;top:0;left:0;right:0;height:2px;
    background:linear-gradient(90deg,var(--red),var(--gold),var(--red));
}

.badge{
    display:inline-flex;align-items:center;gap:.4rem;
    background:rgba(139,48,48,.2);border:1px solid rgba(139,48,48,.3);
    color:var(--gold);font-size:.72rem;font-weight:600;
    padding:.3rem .8rem;border-radius:20px;
    margin-bottom:1rem;text-transform:uppercase;letter-spacing:.1em;
}
.card-title{
    font-family:var(--ff-head);
    font-size:1.7rem;color:var(--text);margin-bottom:.2rem;
}
.card-sub{color:var(--muted);font-size:.84rem;margin-bottom:1.5rem;}

/* Error */
.err{
    background:rgba(231,76,60,.1);border:1px solid rgba(231,76,60,.25);
    border-radius:8px;padding:.7rem 1rem;margin-bottom:1rem;
    color:#ff6b6b;font-size:.85rem;
    display:flex;align-items:center;gap:.5rem;
}

/* Fields */
.fld{margin-bottom:1rem;}
.fld-lbl{
    display:block;font-size:.7rem;font-weight:600;
    color:#888;text-transform:uppercase;letter-spacing:.08em;
    margin-bottom:.4rem;
}
.ir{position:relative;}
.ir-ic{
    position:absolute;left:.9rem;top:50%;transform:translateY(-50%);
    color:#444;font-size:.9rem;pointer-events:none;
}
.ir input{
    width:100%;padding:.78rem 1rem .78rem 2.6rem;
    background:#1E1E1E;border:1.5px solid var(--border);
    border-radius:10px;font-size:.93rem;
    font-family:var(--ff-body);color:var(--text);outline:none;
    transition:border-color .2s,box-shadow .2s,background .2s;
}
.ir input:focus{
    border-color:var(--red);background:#222;
    box-shadow:0 0 0 3px rgba(139,48,48,.15);
}
.ir input::placeholder{color:#444;}
.eyebtn{
    position:absolute;right:.8rem;top:50%;transform:translateY(-50%);
    background:none;border:none;cursor:pointer;
    color:#444;font-size:.9rem;padding:.2rem;
    transition:color .2s;
}
.eyebtn:hover{color:var(--gold);}

/* Submit */
.sbtn{
    width:100%;padding:.88rem;
    background:linear-gradient(135deg,var(--red) 0%,var(--red-lt) 100%);
    color:#fff;border:none;border-radius:10px;
    font-size:.95rem;font-weight:600;
    font-family:var(--ff-body);cursor:pointer;
    letter-spacing:.03em;margin-top:.25rem;
    transition:opacity .2s,transform .15s,box-shadow .2s;
    position:relative;overflow:hidden;
}
.sbtn::after{
    content:'';position:absolute;inset:0;
    background:linear-gradient(135deg,transparent 0%,rgba(255,255,255,.08) 100%);
}
.sbtn:hover{opacity:.92;transform:translateY(-1px);box-shadow:0 6px 24px rgba(139,48,48,.4);}

/* Bottom link */
.back-link{
    text-align:center;margin-top:1.1rem;
    font-size:.83rem;color:var(--muted);
}
.back-link a{color:var(--gold);text-decoration:none;font-weight:500;}
.back-link a:hover{text-decoration:underline;}

/* Security note */
.sec-note{
    display:flex;align-items:center;gap:.5rem;
    margin-top:1.5rem;padding-top:1.25rem;
    border-top:1px solid var(--border);
    font-size:.75rem;color:#444;
}
.sec-dot{width:8px;height:8px;border-radius:50%;background:#2ecc71;flex-shrink:0;}
</style>
</head>
<body>
<div class="login-wrap">

    <div class="logo-area">
        <div class="logo-icon-wrap">&#128218;</div>
        <h1>Gyansetu</h1>
        <p><?php echo COLLEGE_NAME; ?></p>
    </div>

    <div class="card">
        <div class="badge">&#128737; Administrator Access</div>
        <h2 class="card-title">Admin Login</h2>
        <p class="card-sub">Library Management System</p>

        <?php if ($error): ?>
        <div class="err">&#10007; <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="fld">
                <label class="fld-lbl">Email Address</label>
                <div class="ir">
                    <span class="ir-ic">&#9993;</span>
                    <input type="email" name="email" placeholder="admin@stlawrence.edu.np"
                           value="<?php echo sanitize($_POST['email']??''); ?>"
                           required autofocus>
                </div>
            </div>

            <div class="fld">
                <label class="fld-lbl">Password</label>
                <div class="ir">
                    <span class="ir-ic">&#128274;</span>
                    <input type="password" id="pw" name="password"
                           placeholder="Enter admin password" required>
                    <button type="button" class="eyebtn"
                        onclick="var i=document.getElementById('pw');
                                 this.textContent=i.type==='password'?(i.type='text','&#128064;'):(i.type='password','&#128065;')">
                        &#128065;</button>
                </div>
            </div>

            <button type="submit" class="sbtn">Access Dashboard &#8594;</button>
        </form>

        <p class="back-link">
            <a href="<?php echo BASE_URL; ?>/login.php">&#8592; Back to Student Login</a>
        </p>

        <div class="sec-note">
            <div class="sec-dot"></div>
            Secure connection · Authorized personnel only
        </div>
    </div>
</div>
</body>
</html>