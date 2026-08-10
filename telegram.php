<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHA75 Verified Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-bg: #1a232f; 
            --secondary-bg: #212c3b;
            --accent-color: #26a6f2; 
            --text-white: #ffffff;
            --text-gray: #a5b1be;
            --warning-yellow: #ffcc00;
        }

        body {
            background-color: var(--primary-bg);
            color: var(--text-white);
            font-family: 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            width: 92%;
            max-width: 420px;
            background: var(--secondary-bg);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
            border: 1px solid #303d4d;
        }

        .header-title {
            color: var(--accent-color);
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .manager-info {
            background: rgba(38, 166, 242, 0.1);
            border: 1px solid var(--accent-color);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .warning-box {
            background: rgba(255, 204, 0, 0.1);
            border-left: 4px solid var(--warning-yellow);
            padding: 12px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 13px;
            color: #ffeb99;
        }

        .username-box {
            background: #141b24;
            border: 1px solid #303d4d;
            border-radius: 8px;
            padding: 18px;
            font-size: 24px;
            color: var(--accent-color);
            font-weight: bold;
            margin-bottom: 15px;
            letter-spacing: 1px;
        }

        .btn-action {
            background-color: var(--accent-color);
            color: white;
            border: none;
            width: 100%;
            padding: 16px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
        }

        .btn-action:active {
            transform: scale(0.98);
            background-color: #1a8cd8;
        }

        .instructions {
            text-align: left;
            background: rgba(0,0,0,0.3);
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .instructions h4 {
            margin: 0 0 10px 0;
            font-size: 15px;
            color: var(--accent-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .instructions p {
            margin: 6px 0;
            font-size: 13px;
            color: var(--text-gray);
            line-height: 1.4;
        }

        .security-note {
            font-size: 12px;
            color: var(--text-gray);
            margin-top: 15px;
            border-top: 1px solid #303d4d;
            padding-top: 10px;
        }

        #redirect-status {
            font-size: 12px;
            color: var(--accent-color);
            margin-top: 10px;
            display: none;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-title">
        <i class="fa-solid fa-user-shield"></i> SHA75 ভেরিফাইড ম্যানেজার 
    </div>
    
    <div class="manager-info">
        অফিসিয়াল এজেন্ট ও লেনদেন সংক্রান্ত যেকোনো সমস্যার জন্য সরাসরি আমাদের ভেরিফাইড ম্যানেজারের সাথে যোগাযোগ করুন।
    </div>

    <div class="warning-box">
        <i class="fa-solid fa-triangle-exclamation"></i> <b>সতর্কবার্তা:</b> আমাদের ম্যানেজার ছাড়া অন্য কারো সাথে যোগাযোগ করে প্রতারিত হবেন না। আমাদের ভেরিফাইড ম্যানেজার আপনার প্লেয়ার একাউন্টের সকল তথ্য যাচাই করতে সক্ষম।
    </div>

    <div class="username-box" id="username">@sha75bets</div>

    <button class="btn-action" onclick="copyUsername()">
        <i class="fas fa-copy"></i> ইউজারনেম কপি করুন
    </button>

    <div id="redirect-status">ব্রাউজার ডিটেক্ট করা হয়েছে। ৫ সেকেন্ড পর অটোমেটিক টেলিগ্রাম ওপেন হচ্ছে...</div>

    <div class="instructions">
        <h4><i class="fa-brands fa-telegram"></i> কন্টাক্ট করার নিয়ম:</h4>
        <p>১. উপরের বাটন থেকে ইউজারনেমটি <b>কপি</b> করুন।</p>
        <p>২. টেলিগ্রাম অ্যাপের <b>Search</b> বক্সে গিয়ে পেস্ট করুন।</p>
        <p>৩. আমাদের ভেরিফাইড ম্যানেজারের প্রোফাইলে মেসেজ দিন।</p>
    </div>

    <div class="security-note">
        নিরাপত্তার স্বার্থে আপনার পাসওয়ার্ড বা ওটিপি (OTP) কাউকেও শেয়ার করবেন না।
    </div>
</div>

<script>
    const tgUsername = "@sha75bets";
    const tgLink = "https://t.me/sha75bets";

    function copyUsername() {
        const tempInput = document.createElement("textarea");
        tempInput.value = tgUsername;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand("copy");
        document.body.removeChild(tempInput);
        alert("ম্যানেজার ইউজারনেম কপি হয়েছে! এখন টেলিগ্রামে সার্চ করুন।");
    }

    // Redirect logic
    window.onload = function() {
        const userAgent = navigator.userAgent;
        const redirectStatus = document.getElementById('redirect-status');

        // Check if NOT in our App
        if (!userAgent.includes("SHA75-WebView-App")) {
            redirectStatus.style.display = "block";
            
            setTimeout(function() {
                window.location.href = tgLink;
            }, 5000);
        }
    };
</script>

</body>
</html>