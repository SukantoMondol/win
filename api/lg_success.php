<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; background: #fff; }
        .btn-home { background: #1a5c92; color: #fff; padding: 12px 25px; border-radius: 8px; text-decoration: none; display: inline-block; font-weight: bold; margin-top: 20px; }
    </style>
</head>
<body>
    <div style="margin-top: 50px;">
        <img src="https://cdn-icons-png.flaticon.com/512/148/148767.png" width="100">
        <h2 style="color: #28a745;">পেমেন্ট সফল হয়েছে!</h2>
        <p>আপনাকে ড্যাশবোর্ডে ফিরিয়ে নেওয়া হচ্ছে...</p>
        
        <a href="../player/dashboard.php" class="btn-home">ড্যাশবোর্ডে ফিরে যান</a>
    </div>

    <script type="text/javascript">
        // অ্যান্ড্রয়েড অ্যাপের জন্য রিডাইরেক্ট লজিক
        function directRedirect() {
            window.location.replace("../player/dashboard.php");
        }

        // ২ সেকেন্ড পর অটোমেটিক রিডাইরেক্ট
        setTimeout(directRedirect, 2000);

        // হার্ডওয়্যার ব্যাক বাটন হ্যান্ডলিং (অ্যান্ড্রয়েড অ্যাপের জন্য)
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            window.location.replace("../player/dashboard.php");
        };
    </script>
</body>
</html>