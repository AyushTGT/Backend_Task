<!DOCTYPE html>
<html>
<head>
    <title>Reset Your Password</title>
</head>
<body>
    <h2>Reset Your Password</h2>
    <form method="POST" action="/resetPassword">
        <input type="hidden" name="email" value="{{ request('email') }}">
        <div>
            <label>New Password:</label>
            <input type="password" name="password" required minlength="8">
        </div>
        <div>
            <label>Confirm Password:</label>
            <input type="password" name="password_confirmation" required minlength="8">
        </div>
        <button type="submit">Reset Password</button>
    </form>
</body>
</html>