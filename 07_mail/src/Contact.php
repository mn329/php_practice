<?php
require __DIR__ . '/../vendor/autoload.php';

// PHPMailer と Dotenv の名前空間をインポート
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

class Contact
{
    private PHPMailer $mailer;
    private string $template = __DIR__ . "/templates/contact_mail.html";

    // 環境変数をクラスメンバで保持
    private string $from_address;
    private string $from_name;
    private string $host;
    private string $username;
    private string $password;
    private string $encryption;
    private int    $port;

    private string $subject = '[お問い合わせ]ご確認のメール';

    public function __construct()
    {
        // .env 読み込み
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();

        // クラスメンバに $_ENV の値を代入
        // 送信元アドレス: MAIL_FROM_ADDRESS
        $this->from_address = $_ENV['MAIL_FROM_ADDRESS'];
        // 送信元名前: MAIL_FROM_NAME
        $this->from_name    = $_ENV['MAIL_FROM_NAME'];
        // SMTPサーバー: MAIL_HOST
        $this->host         = $_ENV['MAIL_HOST'];
        // SMTPユーザー名: MAIL_USERNAME
        $this->username     = $_ENV['MAIL_USERNAME'];
        // SMTPパスワード: MAIL_PASSWORD
        $this->password     = $_ENV['MAIL_PASSWORD'];
        // 暗号化方式: MAIL_ENCRYPTION
        $this->encryption   = $_ENV['MAIL_ENCRYPTION'];
        // ポート番号: MAIL_PORT
        $this->port         = (int) $_ENV['MAIL_PORT'];

        $this->setupMailer();
    }

    /**
     * メーラー初期設定
     */
    private function setupMailer(): void
    {
        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->SMTPAuth   = true;
        // SMTPサーバーの設定
        $this->mailer->Host       = $this->host;
        // SMTPユーザー名の設定
        $this->mailer->Username   = $this->username;
        // SMTPパスワードの設定
        $this->mailer->Password   = $this->password;
        // 暗号化方式の設定
        $this->mailer->SMTPSecure = $this->encryption;
        // ポート番号の設定
        $this->mailer->Port       = $this->port;
        $this->mailer->CharSet    = 'UTF-8';
        $this->mailer->Encoding   = 'base64';
    }

    /**
     * 入力バリデーション
     */
    public function validate($name, $email, $body)
    {
        if (empty($name) || empty($email) || empty($body)) {
            return "すべてのフィールドを入力してください。";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "有効なメールアドレスを入力してください。";
        }
        return '';
    }

    /**
     * テンプレートを読み込んで置換
     */
    private function loadTemplate($values)
    {
        // テンプレート読み込み
        $template = file_get_contents($this->template);
        // テンプレートに変数を置換
        foreach ($values as $key => $value) {
            $template = str_replace("{{{$key}}}", $value, $template);
        }
        return $template;
    }

    /**
     * メール送信
     */
    public function send($name, $email, $body)
    {
        try {
            // HTMLメール作成
            $html = $this->loadTemplate([
                // 入力値はHTMLとして解釈されないようエスケープしてから埋め込む
                "name"  => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                "email" => htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
                "body"  => nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')),
            ]);

            // 送信元アドレスと名前
            $this->mailer->setFrom($this->from_address, $this->from_name);
            // 送信先アドレスと名前
            $this->mailer->addAddress($email, $name);
            // 返信先アドレスと名前
            $this->mailer->addReplyTo($this->from_address, $this->from_name);
            // HTML形式を有効に
            $this->mailer->isHTML(true);
            // メールタイトル
            $this->mailer->Subject = $this->subject;
            // メール本文
            $this->mailer->Body = $html;

            // メール送信（成功したら true）
            return $this->mailer->send();
        } catch (Exception $e) {
            return $this->mailer->ErrorInfo;
        }
    }
}
