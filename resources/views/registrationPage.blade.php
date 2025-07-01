<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Registration Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-md-4 col-md-offset-4" style="">
                <h4>Registration Page</h4>
                <hr>
                <form action="/registerUser" method="post">
                    
                    
                    <div class="form-group"> 
                        <label for="name">Username</label>
                        <input type="text" class="form-control" id="name" placeholder="Enter username" name="name" value="" required>
                            
                    </div>   
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="text" class="form-control" id="email" placeholder="Enter Email" name="email" value="" required>
                    </div>    
                    <div class="form-group"> 
                        <label for="post">Username</label>
                        <input type="text" class="form-control" id="post" placeholder="Enter Post" name="post" value="" required>
                            
                    </div>   
                    <div class="password">
                        <label for="password">Password</label>
                        <input type="text" class="form-control" id="password" placeholder="Enter Password" name="password" value="" required>
                    </div>   
                    <div class="form-group">
                        <button class="btn btn-block btn-primary" type="submit">Register</button>
                    </div>   
                    <br>
                    <a href="loginb">Already registered, Login</a>
                </form>
            </div>
        </div>
    </div>    

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>
</html>