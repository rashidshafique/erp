<?php $b = \App\Support\UiSettings::brand(); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in — <?php echo e(\App\Support\UiSettings::brand()['name']); ?></title>
<style>body{font-family:system-ui,sans-serif;background:#f1f3f5;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
.card{background:#fff;padding:32px;border-radius:10px;width:340px;box-shadow:0 2px 12px #0002}h1{margin:0 0 18px;font-size:20px}
label{display:block;margin:12px 0 4px;font-size:13px}input[type=email],input[type=password]{width:100%;padding:9px;box-sizing:border-box;border:1px solid #bbb;border-radius:6px}
button{margin-top:18px;width:100%;padding:10px;background:<?php echo e(\App\Support\UiSettings::theme()['accent']); ?>;color:#fff;border:0;border-radius:6px;cursor:pointer}.err{color:#b00020;font-size:13px;margin-top:10px}</style></head>
<body><form class="card" method="POST" action="<?php echo e(route('login.attempt')); ?>"><?php echo csrf_field(); ?>
<?php if($b['logo']): ?><img src="<?php echo e($b['logo']); ?>" alt="<?php echo e($b['name']); ?>" style="display:block;max-width:100%;height:<?php echo e($b['height']); ?>px;object-fit:contain;margin-bottom:12px"><?php endif; ?><h1><?php echo e($b['name']); ?> — sign in</h1>
<label>Email</label><input type="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus>
<label>Password</label><input type="password" name="password" required>
<label><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
<?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="err"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><button type="submit">Sign in</button></form></body></html>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/auth/login.blade.php ENDPATH**/ ?>