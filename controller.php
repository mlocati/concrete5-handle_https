<?php defined('C5_EXECUTE') or die('Access Denied.');

class HandleHttpsPackage extends Package
{
    protected $pkgHandle = 'handle_https';
    protected $appVersionRequired = '5.6.3';
    protected $pkgVersion = '0.9.1';

    public function getPackageName()
    {
        return t('Handle HTTPS');
    }

    public function getPackageDescription()
    {
        return t('Handle http/https protocols for site pages');
    }

    public function install()
    {
        $pkg = parent::install();
        $this->installOrUpgrade($pkg, '');
    }

    public function upgrade()
    {
        $fromVersion = $this->getPackageVersion();
        parent::upgrade();
        $this->installOrUpgrade($this, $fromVersion);
    }

    private function installOrUpgrade($pkg, $fromVersion)
    {
        $at = AttributeType::getByHandle('handle_https');
        if (!is_object($at)) {
            $at = AttributeType::add(
                'handle_https',
                tc('AttributeTypeName', 'HTTPS handling'),
                $pkg
            );
        }
        $akc = AttributeKeyCategory::getByHandle('collection');
        if (is_object($akc)) {
            if (!$akc->hasAttributeKeyTypeAssociated($at)) {
                $akc->associateAttributeKeyType($at);
            }
        }
        $ak = CollectionAttributeKey::getByHandle('handle_https');
        $hhh = Loader::helper('https_handling', 'handle_https');
        /* @var $hhh HttpsHandlingHelper */
        if (!is_object($ak)) {
            $httpDomain = defined('BASE_URL') ? BASE_URL : Config::get('BASE_URL');
            if (!$httpDomain) {
                $httpDomain = 'http://' . $hhh->getRequestDomain();
            }
            $httpsDomain = defined('BASE_URL_SSL') ? BASE_URL_SSL : Config::get('BASE_URL_SSL');
            if (!$httpsDomain) {
                $httpsDomain = 'https://' . $hhh->getRequestDomain();
            }
            $ak = CollectionAttributeKey::add(
                $at,
                array(
                    'akHandle' => 'handle_https',
                    'akName' => tc('AttributeKeyName', 'Page HTTP/HTTPS'),
                    'akIsSearchable' => 1,
                    'akIsSearchableIndexed' => 1,
                    'akIsAutoCreated' => 1,
                    'akIsEditable' => 1,
                    'akIsInternal' => 0,
                    'akEnabled' => 0,
                    'akDefaultRequirement' => $hhh::SSLHANDLING_DOESNOT_MATTER,
                    'akCustomDomains' => 0,
                    'akHTTPDomain' => $httpDomain,
                    'akHTTPSDomain' => $httpsDomain
                ),
                $pkg
            );
        }
    }

    public function uninstall()
    {
        parent::uninstall();
        $db = Loader::db();
        $db->Execute('drop table if exists atHandleHttps');
        $db->Execute('drop table if exists atHandleHttpsConfig');
    }

    public function on_start()
    {
        Loader::helper('https_handling', 'handle_https');
        Events::extend('on_before_render', 'HttpsHandlingHelper', 'handleRequest');
    }
}
