<?php defined('C5_EXECUTE') or die('Access Denied.');

class HttpsHandlingHelper
{
    const SSLHANDLING_REQUIRE_HTTP = 'Require HTTP';
    const SSLHANDLING_REQUIRE_HTTPS = 'Require HTTPS';
    const SSLHANDLING_INHERIT = 'Inherit HTTP/HTTPS handling';
    const SSLHANDLING_DOESNOT_MATTER = 'HTTPS does not matter';

    /**
     * @return array
     */
    public static function getHandlings()
    {
        $handlingIDs = array(
            self::SSLHANDLING_REQUIRE_HTTP,
            self::SSLHANDLING_REQUIRE_HTTPS,
            self::SSLHANDLING_INHERIT,
            self::SSLHANDLING_DOESNOT_MATTER
        );
        $result = array();
        foreach ($handlingIDs as $handlingID) {
            $result[$handlingID] = self::getHandlingName($handlingID);
        }

        return $result;
    }

    public static function getHandlingName($handlingID)
    {
        $result = '';
        if (is_string($handlingID) && strlen($handlingID)) {
          switch ($handlingID) {
            case self::SSLHANDLING_REQUIRE_HTTP:
                $result = t('Require HTTP');
                break;
            case self::SSLHANDLING_REQUIRE_HTTPS:
                $result = t('Require HTTPS');
                break;
            case self::SSLHANDLING_INHERIT:
                $result = t('Inherit HTTP/HTTPS from parent page');
                break;
            case self::SSLHANDLING_DOESNOT_MATTER:
                $result = t("Don't check for HTTP/HTTPS");
                break;
           }
        }

        return $result;
    }

    public static function isHTTPSRequest()
    {
        static $result = null;
        if (is_null($result)) {
            if (isset($_SERVER) && is_array($_SERVER)) {
                if (is_null($result)) {
                    $v = array_key_exists('HTTPS', $_SERVER) ? $_SERVER['HTTPS'] : '';
                    if (is_string($v) && (strlen($v) > 0) && (strcasecmp($v, 'off') !== 0)) {
                        $result = true;
                    }
                }
                if (is_null($result)) {
                    $v = array_key_exists('HTTP_X_FORWARDED_PROTO', $_SERVER) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : '';
                    if (is_string($v) && (strcasecmp($v, 'https') === 0)) {
                        $result = true;
                    }
                }
                if (is_null($result)) {
                    $v = array_key_exists('HTTP_X_FORWARDED_SSL', $_SERVER) ? $_SERVER['HTTP_X_FORWARDED_SSL'] : '';
                    if (is_string($v) && (strlen($v) > 0) && (strcasecmp($v, 'off') !== 0)) {
                        $result = true;
                    }
                }
            }
            if (is_null($result)) {
                $result = false;
            }
        }

        return $result;
    }

    public static function getRequestDomain()
    {
        static $result = null;
        if (is_null($result)) {
            if (isset($_SERVER) && is_array($_SERVER)) {
                if (is_null($result)) {
                    $v = array_key_exists('HTTP_HOST', $_SERVER) ? $_SERVER['HTTP_HOST'] : '';
                    if (is_string($v) && (strlen($v) > 0)) {
                        $result = $v;
                    }
                }
                if (is_null($result)) {
                    $v = array_key_exists('SERVER_NAME', $_SERVER) ? $_SERVER['SERVER_NAME'] : '';
                    if (is_string($v) && (strlen($v) > 0)) {
                        $result = $v;
                        $v = array_key_exists('SERVER_PORT', $_SERVER) ? $_SERVER['SERVER_PORT'] : '';
                        if (!is_int($v)) {
                            $v = (is_string($v) && is_numeric($v)) ? @intval($v) : 0;
                        }
                        if ($v > 0) {
                            switch ($v) {
                                case 80:
                                    if (!self::isHTTPSRequest()) {
                                        $v = 0;
                                    }
                                    break;
                                case 443:
                                    if (self::isHTTPSRequest()) {
                                        $v = 0;
                                    }
                            }
                            if ($v > 0) {
                                $result .= ':' . strval($v);
                            }
                        }
                        $result = $v;
                    }
                }
            }
            if (is_null($result)) {
                $result = '';
            }
        }

        return $result;
    }

    /**
     * @param Page|View|Collection $page
     * @param User $user
     */
    public static function handleRequest($page)
    {
        if (!is_object($page)) {
            return;
        }
        if (is_a($page, 'View')) {
            $page = $page->getCollectionObject();
        }
        if ((!is_object($page)) || (!is_a($page, 'Collection')) || $page->isError()) {
            return;
        }
        $db = Loader::db();
        $ak = null;
        $config = null;
        $rs = $db->Query('select * from atHandleHttpsConfig where akEnabled = 1');
        while ($row = $rs->FetchRow()) {
            $ak = CollectionAttributeKey::getByID($row['akID']);
            if (is_object($ak)) {
                $config = $row;
                break;
            }
        }
        $rs->Close();
        if (!is_object($ak)) {
            return;
        }
        $akPage = $page;
        for (;;) {
            $handling = $akPage->getAttribute($ak);
            if (!(is_string($handling) && strlen($handling))) {
                $handling = $row['akDefaultRequirement'];
                if (!(is_string($handling) && strlen($handling))) {
                    return;
                }
            }
            if ($handling !== self::SSLHANDLING_INHERIT) {
                break;
            }
            $cID = $akPage->getCollectionID();
            if (empty($cID) || ($cID == HOME_CID)) {
                break;
            }
            if (!is_a($akPage, 'Page')) {
                // Need to load the Page object associated to the Collection object we received
                $akPage = Page::getByID($cID, 'ACTIVE');
                if (!is_object($akPage)) {
                    break;
                }
            }
            $parentCID = $akPage->getCollectionParentID();
            if (empty($parentCID)) {
                break;
            }
            $akPage = Page::getByID($parentCID, 'ACTIVE');
            if ((!is_object($akPage)) || $akPage->isError()) {
                break;
            }
        }
        $switchTo = '';
        switch ($handling) {
            case self::SSLHANDLING_REQUIRE_HTTP:
                if (self::isHTTPSRequest()) {
                    $switchTo = 'http';
                }
                break;
            case self::SSLHANDLING_REQUIRE_HTTPS:
                if (!self::isHTTPSRequest()) {
                    $switchTo = 'https';
                }
        }
        if (!strlen($switchTo)) {
            return;
        }
        if (!$config['akRedirectEditors']) {
            $user = User::isLoggedIn() ? new User() : null;
            if (is_object($user) && $user->getUserID()) {
                if(is_a($page, 'Collection')) {
                    $page = Page::getByID($page->getCollectionID());
                }
                $pp = new Permissions($page);
                if (!$pp->isError()) {
                    if ($pp->canEditPageContents() || $pp->canEditPageProperties()) {
                        return;
                    }
                }
            }
        }
        $finalURL = '';
        if ($config['akCustomDomains']) {
            switch ($switchTo) {
                case 'http':
                    $finalURL = $config['akHTTPDomain'];
                    break;
                case 'https':
                    $finalURL = $config['akHTTPSDomain'];
                    break;
            }
        }
        if (!strlen($finalURL)) {
            $finalURL = $switchTo . '://' . self::getRequestDomain();
        }

        $request = Request::get();
        $finalURL = rtrim($finalURL, '/') . trim(DIR_REL, '/') . '/' . @ltrim($request->getRequestPath(), '/');
        if (isset($_SERVER) && is_array($_SERVER) && array_key_exists('QUERY_STRING', $_SERVER) && is_string($_SERVER['QUERY_STRING']) && strlen($_SERVER['QUERY_STRING'])) {
            $finalURL .= '?' . $_SERVER['QUERY_STRING'];
        }
        @ob_clean();
        if((!isset($_POST)) || (!is_array($_POST)) || empty($_POST)) {
            header('Location: ' . $finalURL);
        }
        else {
            ?><!doctype html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html;charset=<?php echo h(APP_CHARSET); ?>">
        <meta charset="<?php echo h(APP_CHARSET); ?>">
        <script type="text/javascript">
        window.onload = function() {
            var F = document.all ? document.all('form') : document.getElementById('form');
            F.submit();
        };
        </script>
    </head>
    <body>
        <form id="form" method="POST" action="<?php echo h($finalURL); ?>"><?php
            foreach($_POST as $key => $value) {
                if(is_array($value)) {
                    foreach($value as $value1) {
                        ?><input type="hidden" name="<?php echo h($key); ?>[]" value="<?php echo h($value1); ?>"><?php
                    }
                }
                else {
                    ?><input type="hidden" name="<?php echo h($key); ?>" value="<?php echo h($value); ?>"><?php
                }
            }
        ?></form>
    </body>
</html><?php
        }
        die();
    }
}
