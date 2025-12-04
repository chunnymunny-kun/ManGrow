<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Generator with Logo</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
</head>
<body>
    <h1>QR Code Generator with Logo</h1>
    <form id="qrForm">
        <label for="text">Enter text:</label>
        <input type="text" id="text" name="text" required>
        <button id="qrSubmit">Generate QR Code</button>
    </form>
    <div id="qrcode"></div>

    <script>
        document.getElementById('text').addEventListener('input', function (e) {
            e.preventDefault(); // Prevent form submission

            const text = document.getElementById('text').value;
            const qrCodeContainer = document.getElementById('qrcode');

            // Clear any existing QR code
            qrCodeContainer.innerHTML = '';

            // Create a canvas element
            const canvas = document.createElement('canvas');
            qrCodeContainer.appendChild(canvas);

            // Generate the QR code on the canvas
            QRCode.toCanvas(canvas, text, {
                width: 300,
                margin: 1,
                color: {
                    dark: '#000000', // Black
                    light: '#ffffff' // White
                }
            }, function (error) {
                if (error) {
                    console.error(error);
                    return;
                }

                // Add the logo to the center of the QR code
                const ctx = canvas.getContext('2d');
                const logo = new Image();
                logo.src = 'images/mangrow-logo.png'; // Replace with the path to your logo image
                logo.onload = function () {
                    const logoSize = 60; // Size of the logo
                    const x = (canvas.width - logoSize) / 2;
                    const y = (canvas.height - logoSize) / 2;
                    ctx.drawImage(logo, x, y, logoSize, logoSize);
                };
            });
        });
    </script>
</body>
</html>
