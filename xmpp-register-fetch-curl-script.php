<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$registrationFormUrl = "https://ejabberd_node:5443/register/new/";

function fetchContent($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Disable SSL host verification
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL peer verification
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return [false, $error_msg];
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http_code === 200, $response];
}

$message = '';
$messageClass = '';

list($success, $registrationFormHtml) = fetchContent($registrationFormUrl);

if (!$success) {
    $message = '<i class="fas fa-times-circle"></i> Error fetching the form. Please try again later.';
    $messageClass = 'error';
} else {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($registrationFormHtml);
    libxml_clear_errors();

    $form = $dom->getElementsByTagName('form')->item(0);
    if (!$form) {
        $message = '<i class="fas fa-times-circle"></i> Error parsing the form.';
        $messageClass = 'error';
    } else {
        $inputs = $form->getElementsByTagName('input');
        $captchas = $dom->getElementsByTagName('img');
        $captchaImage = null;
        $captchaID = null;

        foreach ($captchas as $img) {
            if (strpos($img->getAttribute('src'), 'captcha') !== false) {
                $captchaImage = $img->getAttribute('src') . '?t=' . time(); // Prevent caching
                $captchaID = basename(dirname($img->getAttribute('src')));
                break;
            }
        }

        $customFields = [
            'username' => 'Username',
            'host' => 'Server',
            'password' => 'Password',
            'password2' => 'Confirm Password',
            'key' => 'Enter the text you see',
        ];

        // Begin form
        $customFormHtml = '<form action="' . htmlspecialchars($_SERVER['PHP_SELF']) . '" method="POST" class="registration-form" autocomplete="off">';

        // Generate input fields
        foreach ($inputs as $input) {
            $type = $input->getAttribute('type');
            $name = $input->getAttribute('name');
            $placeholder = isset($customFields[$name]) ? $customFields[$name] : '';
            $value = ''; // Default empty

            if ($name === 'host') {
                $value = 'uuxo.net'; // Prefill 'host' with 'uuxo.net'
            }

            if ($name && $placeholder && $name !== 'key') { // Avoid duplicate CAPTCHA field
                $customFormHtml .= '<input type="' . htmlspecialchars($type) . '" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" placeholder="' . htmlspecialchars($placeholder) . '" required autocomplete="off">';
            }
        }

        // Add CAPTCHA fields if available
        if ($captchaImage) {
            $customFormHtml .= '<div class="captcha-container">';
            $customFormHtml .= '<img src="' . htmlspecialchars($captchaImage) . '" alt="CAPTCHA Image">';
            $customFormHtml .= '</div>';
            $customFormHtml .= '<input type="hidden" name="id" value="' . htmlspecialchars($captchaID) . '">';
            $customFormHtml .= '<input type="text" name="key" placeholder="Enter the text you see" required autocomplete="off">';
        }

        // Button container with Refresh and Register buttons
        $customFormHtml .= '<div class="button-container">';
        $customFormHtml .= '<button type="button" class="refresh-captcha" title="Refresh CAPTCHA" aria-label="Refresh CAPTCHA"><i class="fas fa-sync-alt"></i></button>';
        $customFormHtml .= '<button type="submit" name="register" class="register-button" id="register-button">Register</button>';
        $customFormHtml .= '</div>';

        // End form
        $customFormHtml .= '</form>';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $host = $_POST['host'] ?? '';
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $key = $_POST['key'] ?? '';
    $captchaID = $_POST['id'] ?? '';

    if ($password !== $password2) {
        $message = 'Failed';
        $messageClass = 'error';
    } else {
        $postData = http_build_query([
            'username' => $username,
            'host' => $host,
            'password' => $password,
            'password2' => $password2,
            'key' => $key,
            'id' => $captchaID
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $registrationFormUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $message = 'Failed';
            $messageClass = 'error';
        } else {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($http_code == 200) {
                $message = 'Success';
                $messageClass = 'success';
            } else {
                $message = 'Failed';
                $messageClass = 'error';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #181A1B;
            color: #2c3e50;
            font-family: Arial, sans-serif;
        }

        .registration-form {
            display: flex;
            flex-direction: column;
            width: 90%;
            max-width: 400px;
            padding: 15px;
            background-color: #181A1B;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .registration-form input {
            margin-bottom: 10px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 3px;
            background-color: #fff;
            font-size: 14px;
            color: #333;
        }

        .button-container {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            margin-top: 10px;
        }

        .refresh-captcha {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 40px;
            height: 40px;
            background-color: #476C1D;
            color: #fff;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 18px;
        }

        .refresh-captcha:hover {
            background-color: #286035;
        }

        .register-button {
            flex-grow: 1;
            padding: 10px;
            background-color: #476C1D;
            color: #fff;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 16px;
        }

        .register-button:hover {
            background-color: #286035;
        }

        .register-button.success {
            background-color: #27ae60;
            cursor: default;
        }

        .register-button.error {
            background-color: #c0392b;
            cursor: default;
        }

        .captcha-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 10px;
        }

        .captcha-container img {
            width: 100%;
            height: auto;
            margin-bottom: 5px;
        }

        @media (max-width: 480px) {
            .refresh-captcha {
                width: 35px;
                height: 35px;
                font-size: 16px;
            }

            .register-button {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>

    <?php
    // Render form HTML
    echo isset($customFormHtml) ? $customFormHtml : '';
    
    if (!empty($message)) {
        echo '<script>
                document.addEventListener("DOMContentLoaded", function () {
                    const registerButton = document.getElementById("register-button");
                    if (registerButton) {
                        registerButton.classList.add("' . $messageClass . '");
                        registerButton.innerHTML = "' . $message . '";
                        registerButton.disabled = true;
                        setTimeout(function() {
                            registerButton.classList.remove("' . $messageClass . '");
                            registerButton.innerHTML = "Register";
                            registerButton.disabled = false;
                        }, 5000);
                    }
                });
            </script>';
    }
    ?>
    
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const refreshButton = document.querySelector(".refresh-captcha");
            const captchaImage = document.querySelector(".captcha-container img");

            if (refreshButton && captchaImage) {
                refreshButton.addEventListener("click", function () {
                    captchaImage.src = captchaImage.src.split("?")[0] + "?" + new Date().getTime();
                });
            }
        });
    </script>
</body>
</html>
