<?php
require_once 'config/db.php';
$email='admin@oms.com'; $check=$conn->prepare('SELECT id FROM users WHERE email=?'); $check->bind_param('s',$email); $check->execute();
if(!$check->get_result()->fetch_assoc()){ $name='System Admin'; $password=password_hash('admin123',PASSWORD_DEFAULT); $role='admin'; $address='OMS Office'; $s=$conn->prepare('INSERT INTO users(name,email,password,role,address) VALUES(?,?,?,?,?)'); $s->bind_param('sssss',$name,$email,$password,$role,$address); $s->execute(); }
echo 'Setup complete. Admin login: admin@oms.com / admin123. Delete setup.php after use.';
?>