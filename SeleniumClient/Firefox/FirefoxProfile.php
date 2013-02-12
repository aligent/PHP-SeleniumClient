<?php

namespace SeleniumClient\Firefox;

/**
 * Creates a Firefox profile that can be passed through to the browser instance
 * via the webdriver.
 * Allows adding Firefox extensions and preferences.
 * 
 * @author Luke Mills <luke@aligent.com.au>
 */
class FirefoxProfile {

    protected $extensionsDirName = 'extensions';
    protected $userPrefsFilename = 'user.js';
    protected $tempDirName;
    protected $additionalPrefs = array();

    public function __construct() {
        $this->tempDirName = tempnam(sys_get_temp_dir(), "webdriver_firefox_profile_");
        unlink($this->tempDirName);
        mkdir($this->tempDirName);
        $this->extensionsDirName = $this->tempDirName . DIRECTORY_SEPARATOR . $this->extensionsDirName;
        mkdir($this->extensionsDirName);
        $this->userPrefsFilename = $this->tempDirName . DIRECTORY_SEPARATOR . $this->userPrefsFilename;
    }

    public function __destruct() {
        self::rmdir_recursive($this->tempDirName);
        
        // Don't know where these files are created, but they need to be cleaned up.
        // @TODO: investigate where these dirs are created, and find a better solution.
        foreach (glob(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'webdriver*duplicated') as $dir) {
            self::rmdir_recursive($dir);
        }
    }

    /**
     * Attempt to add an extension to install into this instance.
     * 
     * @param string $extensionToInstall The filename for the extension to add.
     */
    public function addExtension($extensionToInstall) {
        // @TODO: Attempt to validate whether the extension is a zip, xpi, or directory
        // For now assume zip (xpi is a zip format file)

        $zip = new \ZipArchive();
        $zip->open($extensionToInstall);

        $id = $this->readIdFromInstallRdf($zip->getFromName('install.rdf'));

        $extensionDirName = $this->extensionsDirName . DIRECTORY_SEPARATOR . $id . '.xpi';

        copy($extensionToInstall, $extensionDirName);
    }

    public function setPreference($key, $value) {
        $this->additionalPrefs[$key] = $value;
        return $this;
    }

    public function getProfile() {

        $tmpFilename = $this->tempDirName . '.zip';
        
        $zip = new \ZipArchive();
        $zip->open($tmpFilename, \ZipArchive::CREATE);
        $this->writePreferencesFile();
        $zip->addFile($this->userPrefsFilename, basename($this->userPrefsFilename));
        $zip->addEmptyDir('extensions');
        foreach (glob($this->extensionsDirName . DIRECTORY_SEPARATOR . '*') as $file) {
            if (is_file($file)) {
                $zip->addFile($file, 'extensions/' . basename($file));
            }
        }
        $zip->close();

        $base64 = base64_encode(file_get_contents($tmpFilename));

        unlink($tmpFilename);

        return $base64;
    }

    public function __toString() {
        return $this->getProfile();
    }

    protected function writePreferencesFile() {
        $handle = fopen($this->userPrefsFilename, 'w');
        foreach ($this->additionalPrefs as $key => $value) {
            fwrite($handle, sprintf('user_pref("%s", "%s");', $key, $value) . PHP_EOL);
        }
        fclose($handle);
    }

    /**
     * Extracts the id from the install.rdf file
     * @param string $rdf
     * @return string
     */
    protected function readIdFromInstallRdf($rdf) {
        $xml = new \SimpleXMLElement($rdf);
        $xml->registerXPathNamespace('em', 'http://www.mozilla.org/2004/em-rdf#');
        $result = $xml->xpath('//em:id');
        return '' . $result[0];
    }

    private static function rmdir_recursive($dir) {
        foreach (scandir($dir) as $file) {
            if ('.' === $file || '..' === $file)
                continue;
            if (is_dir("$dir/$file"))
                self::rmdir_recursive("$dir/$file");
            else
                unlink("$dir/$file");
        }
        rmdir($dir);
    }

}