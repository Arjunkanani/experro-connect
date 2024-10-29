<?php
namespace Experro\Connect\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;

class System extends Template
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * Constructor
     * 
     * @param Template\Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param DirectoryList $directoryList
     * @param ResourceConnection $resourceConnection
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        DirectoryList $directoryList,
        ResourceConnection $resourceConnection,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->directoryList = $directoryList;
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context, $data);
    }

    /**
     * Get CRON status
     * 
     * @return array
     */
    public function getCronStatus()
    {
        $cronEnabled = $this->scopeConfig->getValue(
            'crontab/default/jobs/magento_cron/schedule/cron_expr',
            ScopeInterface::SCOPE_STORE
        );

        return [
            'message' => $cronEnabled ? 'CRON is enabled' : 'CRON is disabled. Please enable Default Magento CRON.',
            'class' => $cronEnabled ? 'status-success' : 'status-error'
        ];
    }

    /**
     * Get PHP Version
     * 
     * @return array
     */
    public function getPhpVersion()
    {
        $requiredPhpVersion = '7.4.0'; // Example version
        $currentPhpVersion = phpversion();

        return [
            'message' => version_compare($currentPhpVersion, $requiredPhpVersion, '<')
                ? "PHP version must be $requiredPhpVersion or higher. Current version is $currentPhpVersion."
                : "PHP version is acceptable ($currentPhpVersion).",
            'class' => version_compare($currentPhpVersion, $requiredPhpVersion, '<') ? 'status-error' : 'status-success'
        ];
    }

    /**
     * Get Required PHP Extensions
     * 
     * @return array
     */
    public function getPhpExtensions()
    {
        $requiredExtensions = ['curl', 'gd', 'intl', 'mbstring', 'pdo_mysql', 'soap', 'xsl', 'zip'];
        $installedExtensions = get_loaded_extensions();
        $missingExtensions = [];

        foreach ($requiredExtensions as $extension) {
            if (!in_array($extension, $installedExtensions)) {
                $missingExtensions[] = $extension;
            }
        }

        $extensionStatus = empty($missingExtensions)
            ? 'All required PHP extensions are installed.'
            : 'Missing PHP extensions: ' . implode(', ', $missingExtensions) . '.';

        return [
            'message' => $extensionStatus,
            'class' => empty($missingExtensions) ? 'status-success' : 'status-error'
        ] + [
            'installed' => 'Installed PHP extensions: ' . implode(', ', $installedExtensions) . '.'
        ];
    }

    /**
     * Get Directory Permissions
     * 
     * @return array
     */
    public function getDirectoryPermissions()
    {
        $directories = [
            $this->directoryList->getPath(DirectoryList::VAR_DIR),
            $this->directoryList->getPath(DirectoryList::MEDIA),
            $this->directoryList->getPath(DirectoryList::STATIC_VIEW),
            $this->directoryList->getPath(DirectoryList::GENERATION),
            $this->directoryList->getPath(DirectoryList::APP) . '/etc'
        ];

        $nonWritable = [];
        foreach ($directories as $directory) {
            if (!is_writable($directory)) {
                $nonWritable[] = $directory;
            }
        }

        return [
            'message' => empty($nonWritable)
                ? 'All required directories are writable.'
                : 'Non-writable directories: ' . implode(', ', $nonWritable) . '.',
            'class' => empty($nonWritable) ? 'status-success' : 'status-error'
        ];
    }

    /**
     * Get Database Connection Status
     * 
     * @return array
     */
    public function getDatabaseConnectionStatus()
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $connection->getServerVersion(); // Test database connection
            return [
                'message' => 'Database connection is successful.',
                'class' => 'status-success'
            ];
        } catch (\Exception $e) {
            return [
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'class' => 'status-error'
            ];
        }
    }


}
