<audio id="notifSound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="none"></audio>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ১. Service Worker রেজিস্টার করা (মোবাইলের জন্য বাধ্যতামূলক)
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').then(function(registration) {
            console.log('Service Worker Registered');
        });
    }

    // ২. পারমিশন চাওয়া
    if (Notification.permission !== "granted" && Notification.permission !== "denied") {
        Notification.requestPermission();
    }

    // ৩. চেক শুরু
    setInterval(checkForNewTransactions, 5000);
});

function checkForNewTransactions() {
    const apiURL = '/api/check_notification.php'; // আপনার পাথ অনুযায়ী

    fetch(apiURL)
    .then(response => response.json())
    .then(data => {
        if (data && data.length > 0) {
            playNotificationSound(); // সাউন্ড বাজবে

            data.forEach(item => {
                sendMobileNotification(item.title, item.body, item.url);
            });
        }
    })
    .catch(error => console.log('Checking...'));
}

function playNotificationSound() {
    var audio = document.getElementById("notifSound");
    audio.currentTime = 0;
    var playPromise = audio.play();
    if (playPromise !== undefined) {
        playPromise.catch(error => console.log("Audio need interaction"));
    }
}

// ৪. মোবাইল এবং পিসির জন্য ইউনিভার্সাল নোটিফিকেশন ফাংশন
function sendMobileNotification(title, body, url) {
    if (Notification.permission === "granted") {
        
        // অপশন কনফিগারেশন
        const options = {
            body: body,
            icon: 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png',
            vibrate: [200, 100, 200], // মোবাইলে ভাইব্রেট হবে
            data: { url: url } // ক্লিক লিংক
        };

        // মেথড ১: মোবাইলের জন্য Service Worker দিয়ে চেষ্টা করবে
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.ready.then(function(registration) {
                registration.showNotification(title, options);
            });
        } 
        // মেথড ২: পিসির জন্য বা যদি SW কাজ না করে
        else {
            const notification = new Notification(title, options);
            notification.onclick = function(event) {
                event.preventDefault();
                window.open(url, '_self');
            };
        }
    }
}
</script>