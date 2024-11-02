<?php

namespace App\Controllers;

use App\Database;
use App\DTO\LoginResponseDTO;
use App\Models\BlacklistToken;
use App\Models\Role;
use App\Models\Session;
use App\Models\User;
use App\Models\Profile;
use App\Utils\TokenGenerator;
use DateTimeImmutable;
use Exception;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Lcobucci\JWT\Token\Parser;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Illuminate\Database\Capsule\Manager as Capsule;

class AuthController
{
//    public function login(): false|string
//    {
//        $data = json_decode(file_get_contents('php://input'), true);
//        if (!isset($data['email']) || !isset($data['password'])) {
//            return json_encode(['error' => 'Email và mật khẩu là bắt buộc.'], JSON_UNESCAPED_UNICODE);
//        }
//
//        $user = User::where('email', $data['email'])->first();
//        if (!$user || !password_verify($data['password'], $user->password)) {
//            return json_encode(['error' => 'Email hoặc password không chính xác.'], JSON_UNESCAPED_UNICODE);
//        }
//
//        $role = Role::where('id', $user->role_id)->first();
//        $roleName = $role->name;
//
//        $profile = Profile::where('user_id', $user->id)->first(); // Lấy profile dựa trên user_id
//        if (!$profile) {
//            return json_encode(['error' => 'Không tìm thấy thông tin hồ sơ.'], JSON_UNESCAPED_UNICODE);
//        }
//
//        $response = new LoginResponseDTO(TokenGenerator::generateAccessToken($user->id,$profile->id), TokenGenerator::generateRefreshToken($user->id,$profile->id));
//        return json_encode($response);
//    }


    public function login(): false|string
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['email']) || !isset($data['password'])) {
                http_response_code(400);
                return json_encode(['error' => 'Email và mật khẩu là bắt buộc.'], JSON_UNESCAPED_UNICODE);
            }

            $user = User::where('email', $data['email'])->first();
            if (!$user || !password_verify($data['password'], $user->password)) {
                http_response_code(401);
                return json_encode(['error' => 'Email hoặc password không chính xác.'], JSON_UNESCAPED_UNICODE);
            }

            $role = Role::find($user->role_id);
            if (!$role) {
                http_response_code(400);
                return json_encode(['error' => 'Không tìm thấy thông tin quyền.'], JSON_UNESCAPED_UNICODE);
            }

            $profile = Profile::where('user_id', $user->id)->first();
            if (!$profile) {
                http_response_code(400);
                return json_encode(['error' => 'Không tìm thấy thông tin hồ sơ.'], JSON_UNESCAPED_UNICODE);
            }

            // Tạo access token và refresh token mới
            $accessToken = TokenGenerator::generateAccessToken($user->id, $profile->id);
            $refreshToken = TokenGenerator::generateRefreshToken($user->id, $profile->id);

            // Cập nhật hoặc tạo mới session
            Session::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'token' => $refreshToken,
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+14 days'))
                ]
            );

            // Xóa các token trong blacklist nếu có
            Capsule::table('blacklist_tokens')
                ->where('user_id', $user->id)
                ->delete();

            http_response_code(200);
            return json_encode(new LoginResponseDTO($accessToken, $refreshToken));

        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            http_response_code(500);
            return json_encode(['error' => 'Lỗi server: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function logout()
    {
        try {
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

            if (!$token) {
                http_response_code(401);
                return json_encode(['error' => 'Token không tồn tại'], JSON_UNESCAPED_UNICODE);
            }

            // Parse token để lấy thông tin
            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $userId = $parsedToken->claims()->get('id');
            $expires = $parsedToken->claims()->get('exp');

            // Thêm token vào blacklist sử dụng Query Builder
            Capsule::table('blacklist_tokens')->insert([
                'token' => $token,
                'user_id' => $userId,
                'expires_at' => date('Y-m-d H:i:s', $expires->getTimestamp()),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            http_response_code(200);
            return json_encode(['message' => 'Đăng xuất thành công'], JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            error_log($e->getMessage());
            http_response_code(500);
            return json_encode(['error' => 'Lỗi server: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getSessionToken($userId) {
        $session = Session::where('user_id', $userId)->first();
        if ($session) {
            return $session->token;
        }
        return null;
    }

    public function refreshToken()
    {
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        $token = str_replace('Bearer ', '', $authHeader);

        if (empty($token)) {
            error_log("Token is missing in Authorization header");
            http_response_code(401);
            echo json_encode(['error' => 'Token is required']);
            return false;
        }

        $parser = new \Lcobucci\JWT\Token\Parser(new JoseEncoder());
        try {
            $parsedToken = $parser->parse($token);
            assert($parsedToken instanceof Plain);
            $now = new DateTimeImmutable();
            if ($parsedToken->isExpired($now)) {
                error_log("Token is expired");
                http_response_code(401);
                echo json_encode(['error' => 'Token has expired']);
                return false;
            }

            $userId = $parsedToken->claims()->get('id');
            $response = new LoginResponseDTO(TokenGenerator::generateAccessToken($userId), TokenGenerator::generateRefreshToken($userId));
            echo json_encode($response);
            return true;

        } catch (CannotDecodeContent|InvalidTokenStructure|UnsupportedHeaderFound $e) {
            error_log($e->getMessage());
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token']);
            return false;
        }
    }

    public function checkEmailExistence($email)
    {
        // API endpoint và API key
        $apiEndpoint = 'https://emailverification.whoisxmlapi.com/api/v3';
        $apiKey = 'at_kalegIeEx43vPpE6dVkBBS5BUWJ56';

        // Tạo URL cho yêu cầu
        $url = $apiEndpoint . '?apiKey=' . $apiKey . '&emailAddress=' . urlencode($email);

        // Khởi tạo một cURL session
        $curl = curl_init();

        // Thiết lập các tùy chọn của cURL
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new Exception('cURL error: ' . $error);
        }

        curl_close($curl);

        $responseData = json_decode($response, true);

        if (isset($responseData['emailExists']) && $responseData['emailExists'] === true) {
            return true; // Email tồn tại
        } else {
            return false; // Email không tồn tại
        }
    }

    public function register()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $user = new User();
            $error = $user->validate($data);
            if (!empty($error)) {
                http_response_code(400);
                echo json_encode(['error' => $error]);
                return;
            }

            if (!isset($data['email']) || !isset($data['password'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Email and password are required']);
                return;
            }

            $apiKey = '309a54ab876145c988eef7d25a830a1d';
            $emailToCheck = urlencode($data['email']);
            $apiUrl = "https://emailvalidation.abstractapi.com/v1/?api_key=$apiKey&email=$emailToCheck";

            // Initialize cURL.
            $ch = curl_init();

            // Set the URL that you want to GET by using the CURLOPT_URL option.
            curl_setopt($ch, CURLOPT_URL, $apiUrl);

            // Set CURLOPT_RETURNTRANSFER so that the content is returned as a variable.
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Set CURLOPT_FOLLOWLOCATION to true to follow redirects.
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            // Execute the request.
            $dataNew = curl_exec($ch);

            // Close the cURL handle.
            curl_close($ch);

            // Print the data out onto the page.
            $responseData = json_decode($dataNew, true);

            if ($responseData['deliverability'] === 'UNDELIVERABLE') {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid or non-existent email']);
                return;
            }

            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $data['role_id'] = $data['role_id'] ?? 1;

            $role = Role::find($data['role_id']);
            if (!$role) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid role ID']);
                return;
            }

            $createdUser = User::create([
                'email' => $data['email'],
                'password' => $hashedPassword,
                'role_id' => $data['role_id']
            ]);

            $fullName = trim($data['name']);
            $nameParts = explode(' ', $fullName);
            $lastName = array_pop($nameParts);
            $firstName = implode(' ', $nameParts);

            $profileData = [
                'user_id' => $createdUser->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $data['phone'] ?? null,
                'birthday' => $data['birthday'] ?? null,
                'avatar' => $data['avatar'] ?? null,
                'gender' => $data['gender'] ?? null
            ];

            Profile::create($profileData);

            http_response_code(201);
            echo json_encode($createdUser);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error registering user: ' . $e->getMessage()]);
        }
    }

    public function changePassword(): string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['user_id']) || !isset($data['old_password']) || !isset($data['new_password'])) {
            return json_encode(['error' => 'Thiếu thông tin.'], JSON_UNESCAPED_UNICODE);
        }
        $userId = $data['user_id'];
        $oldPassword = $data['old_password'];
        $newPassword = $data['new_password'];

        $user = User::find($userId);
        if (!$user) {
            return json_encode(['error' => 'Người dùng không tồn tại.'], JSON_UNESCAPED_UNICODE);
        }

        if (!password_verify($oldPassword, $user->password)) {
            return json_encode(['error' => 'Mật khẩu cũ không chính xác.'], JSON_UNESCAPED_UNICODE);
        }

        $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $user->password = $hashedNewPassword;
        $user->save();

        return json_encode(['message' => 'Mật khẩu đã được thay đổi thành công.'], JSON_UNESCAPED_UNICODE);
    }

    public function getProfile()
    {
        $headers = apache_request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

        if (!$token) {
            return json_encode(['error' => 'Token không tồn tại'], JSON_UNESCAPED_UNICODE);
        }

        try {
            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);

            $userId = $parsedToken->claims()->get('id');
            $user = User::find($userId);

            if (!$user) {
                return json_encode(['error' => 'Người dùng không tồn tại'], JSON_UNESCAPED_UNICODE);
            }

            $profile = Profile::where('user_id', $userId)->first();
            if (!$profile) {
                return json_encode(['error' => 'Profile không tồn tại cho user_id này'], JSON_UNESCAPED_UNICODE);
            }

            $role = Role::find($user->role_id);

            return json_encode([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role->name,
                'first_name' => $profile->first_name,
                'last_name' => $profile->last_name,
                'phone' => $profile->phone,
                'birthday' => $profile->birthday,
                'avatar' => $profile->avatar,
                'gender' => $profile->gender
            ], JSON_UNESCAPED_UNICODE);
        } catch (CannotDecodeContent|InvalidTokenStructure|UnsupportedHeaderFound $e) {
            return json_encode(['error' => 'Token không hợp lệ'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function forgotPassword()
    {
        // Get the input data from POST request
        $data = json_decode(file_get_contents('php://input'), true);

        // Check if the email is provided
        if (!isset($data['email'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email is required']);
            return;
        }

        $email = $data['email'];

        // Validate the email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email format']);
            return;
        }

        // Look up the user in the database
        $user = User::where('email', $email)->first();
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }

        // Generate a unique token for the password reset
        $resetToken = bin2hex(random_bytes(16));
        $resetLink = "http://localhost:8000/api/v1/auth/reset_password?token=$resetToken";

        // Save the token to the database associated with the user
        $user->reset_password_token = $resetToken;
        $user->token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $user->save();

        // Send the reset link via email
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->SMTPDebug = 2;
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true; // Enable SMTP authentication
            $mail->Username   = 'thiennguyen130922@gmail.com';
            $mail->Password   = 'khwemtnnafdhnacy'; // Use the app password here
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('thiennguyen130922@gmail.com', 'Your App Name');
            $mail->addAddress($email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $mail->Body    = "Click <a href='$resetLink'>here</a> to reset your password. This link will expire in 1 hour.";
            $mail->AltBody = "Copy and paste the following link in your browser to reset your password: $resetLink";

            $mail->send();
            http_response_code(200);
            echo json_encode(['message' => 'Password reset link has been sent to your email address']);
        } catch (PHPMailerException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Mailer Error: ' . $mail->ErrorInfo]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'General error: ' . $e->getMessage()]);
        }
    }

    public function resetPassword()
    {
        // Get the input data from POST request
        $data = json_decode(file_get_contents('php://input'), true);

        // Check if the token and new password are provided
        if (!isset($data['token']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Token and password are required']);
            return;
        }

        $token = $data['token'];
        $newPassword = $data['password'];

        // Look up the user in the database by the reset token
        $user = User::where('reset_password_token', $token)
            ->where('token_expiry', '>', date('Y-m-d H:i:s'))
            ->first();

        if (!$user) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or expired token']);
            return;
        }

        // Update the user's password
        $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
        $user->reset_password_token = null;
        $user->token_expiry = date('Y-m-d H:i:s');
        $user->save();

        http_response_code(200);
        echo json_encode(['message' => 'Password has been reset successfully']);
    }

    public function checkToken()
    {
        if (!isset($_GET['token'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Token and password are required']);
            return;
        }

        $token = $_GET['token'];

        // Look up the user in the database by the reset token
        $user = User::where('reset_password_token', $token)
            ->where('token_expiry', '>', date('Y-m-d H:i:s'))
            ->first();

        if (!$user) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or expired token']);
            return;
        }

        http_response_code(200);
        echo json_encode(['message' => $token]);
    }
}