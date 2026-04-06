<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($subjectLine); ?></title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 15px;
            line-height: 1.7;
            color: #222222;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }
        .wrapper {
            max-width: 560px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #e5e5e5;
            font-size: 12px;
            color: #888888;
        }
    </style>
</head>
<body>
<div class="wrapper">

    
    <?php $__currentLoopData = explode("\n", $emailBody); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if(trim($line)): ?>
            <p><?php echo e($line); ?></p>
        <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    <!-- <div class="footer">
        <p>
            This email was sent to <?php echo e($lead->email); ?> on behalf of
            <strong><?php echo e($senderName); ?></strong> at <strong><?php echo e($senderCompany); ?></strong>.<br>
            If you would prefer not to receive further emails, please reply with "unsubscribe".
        </p>
    </div> -->

</div>
</body>
</html>
<?php /**PATH /var/www/html/resources/views/emails/outreach.blade.php ENDPATH**/ ?>