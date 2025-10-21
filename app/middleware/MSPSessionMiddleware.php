// app/middleware/MSPSessionMiddleware.php
class MSPSessionMiddleware {
    public function handle() {
        if (isset($_GET['msp_token'])) {
            // Validate MSP token and auto-login parent
            $mspService = new MSPService();
            $parent = $mspService->validateParentSession($_GET['msp_token']);
            
            if ($parent) {
                $_SESSION['parent_id'] = $parent['id'];
                $_SESSION['role'] = 'parent';
                header("Location: parent_dashboard.php");
                exit;
            }
        }
    }
}