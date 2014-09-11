<?php defined('C5_EXECUTE') or die('Access Denied.');

Loader::model('attribute/types/default/controller');

class HandleHttpsAttributeTypeController extends AttributeTypeController
{
    protected $searchIndexFieldDefinition = 'C 30 NULL';

    /**
     * @param $node SimpleXMLElement
     * @return SimpleXMLElement
     */
    public function exportKey($node)
    {
        $this->load();
        $type = $node->addChild('type');
        $type->addAttribute('enabled', $this->akEnabled);
        $type->addAttribute('redirectEditors', $this->akRedirectEditors);
        $type->addAttribute('defaultRequirement', $this->akDefaultRequirement);
        $type->addAttribute('customDomains', $this->akCustomDomains);
        $type->addAttribute('httpDomain', $this->akHTTPDomain);
        $type->addAttribute('httpsDomain', $this->akHTTPSDomain);

        return $node;
    }

    /**
     * @param $node SimpleXMLElement
     */
    public function importKey($node)
    {
        if (isset($node->type)) {
            $data = array(
                'akEnabled' => $node->type['enabled'],
                'akRedirectEditors' => $node->type['redirectEditors'],
                'akDefaultRequirement' => $node->type['defaultRequirement'],
                'akCustomDomains' => $node->type['customDomains'],
                'akHTTPDomain' => $node->type['httpDomain'],
                'akHTTPSDomain' => $node->type['httpsDomain']
            );
            $this->saveKey($data);
        }
    }

    /**
     * @param $data array
     */
    public function saveKey($data)
    {
        if (!is_array($data)) {
            $data = array();
        }
        $ak = $this->getAttributeKey();
        $db = Loader::db();
        $hhh = Loader::helper('https_handling', 'handle_https');
        /* @var $hhh HttpsHandlingHelper */
        $akEnabled = empty($data['akEnabled']) ? 0 : 1;
        $akRedirectEditors = empty($data['akRedirectEditors']) ? 0 : 1;
        $akDefaultRequirement = $data['akDefaultRequirement'];
        if (!(is_string($akDefaultRequirement) && array_key_exists($akDefaultRequirement, $hhh->getHandlings()))) {
            $akDefaultRequirement = $hhh::SSLHANDLING_DOESNOT_MATTER;
        }
        $akCustomDomains = empty($data['akCustomDomains']) ? 0 : 1;
        $akHTTPDomain = is_string($data['akHTTPDomain']) ? $data['akHTTPDomain'] : '';
        $akHTTPSDomain = is_string($data['akHTTPSDomain']) ? $data['akHTTPSDomain'] : '';
        $db->Replace(
            'atHandleHttpsConfig',
            array(
                'akID' => $ak->getAttributeKeyID(),
                'akEnabled' => $akEnabled,
                'akRedirectEditors' => $akRedirectEditors,
                'akDefaultRequirement' => $akDefaultRequirement,
                'akCustomDomains' => $akCustomDomains,
                'akHTTPDomain' => $akHTTPDomain,
                'akHTTPSDomain' => $akHTTPSDomain
            ),
            array('akID'),
            true
        );
        if ($akEnabled) {
            $db->Execute('update atHandleHttpsConfig set akEnabled = 0 where akID <> ?', $ak->getAttributeKeyID());
        }
    }

    /**
     *
     */
    public function deleteKey()
    {
        $db = Loader::db();
        $db->Execute('delete from atHandleHttpsConfig where akID = ?', array($this->getAttributeKey()->getAttributeKeyID()));
        foreach ($this->attributeKey->getAttributeValueIDList() as $avID) {
            $db->Execute('delete from atHandleHttps where avID = ?', array($avID));
        }
    }

    /**
     * @param $newAK AttributeKey
     */
    public function duplicateKey($newAK)
    {
        $this->load();
        Loader::db()->Execute(
            'insert into atHandleHttpsConfig set
                akID = ?,
                akEnabled = ?,
                akRedirectEditors = ?,
                akDefaultRequirement = ?,
                akCustomDomains = ?,
                akHTTPDomain = ?,
                akHTTPSDomain = ?
            ',
            array(
                $newAK->getAttributeKeyID(),
                0,
                $this->akRedirectEditors,
                $this->akDefaultRequirement,
                $this->akCustomDomains,
                $this->akHTTPDomain,
                $this->akHTTPSDomain
            )
        );
    }

    /**
     * @param string $value
     */
    public function saveValue($value)
    {
        if (is_string($value) && strlen($value)) {
            $hhh = Loader::helper('https_handling', 'handle_https');
            /* @var $hhh HttpsHandlingHelper */
            if (!array_key_exists($value, $hhh->getHandlings())) {
                $value = '';
            }
        } else {
            $value = '';
        }
        Loader::db()->Replace('atHandleHttps', array('avID' => $this->getAttributeValueID(), 'value' => $value), 'avID', true);
    }

    /**
     * @return string
     */
    public function getValue()
    {
        $result = '';
        $value = Loader::db()->GetOne("select value from atHandleHttps where avID = ?", array($this->getAttributeValueID()));
        if (is_string($value)) {
            $hhh = Loader::helper('https_handling', 'handle_https');
               /* @var $hhh HttpsHandlingHelper */
               if (array_key_exists($value, $hhh->getHandlings())) {
                $result = $value;
            }
        }

        return $result;
    }

    /**
     * @return string
     */
    private function getValueWithFallback()
    {
        $result = $this->getValue();
        if (!strlen($result)) {
            $this->load();
            $result = $this->akDefaultRequirement;
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getDisplayValue()
    {
        $hhh = Loader::helper('https_handling', 'handle_https');
        /* @var $hhh HttpsHandlingHelper */

        return $hhh->getHandlingName($this->getValueWithFallback());
    }
    /**
     * @return string
     */
    public function getDisplaySanitizedValue()
    {
        return h($this->getDisplayValue());
    }

    /**
     *
     */
    public function search()
    {
        $hhh = Loader::helper('https_handling', 'handle_https');
        /* @var $hhh HttpsHandlingHelper */
        $fh = Loader::helper('form');
        /* @var $fh FormHelper */
        echo $fh->select('value', $hhh->getHandlings());
    }

    /**
     * @param DatabaseItemList $list
     * @return DatabaseItemList
     */
    public function searchForm($list)
    {
        $value = $this->request('value');
        if (is_string($value) && strlen($value)) {
            $hhh = Loader::helper('https_handling', 'handle_https');
               /* @var $hhh HttpsHandlingHelper */
            if (array_key_exists($value, $hhh->getHandlings())) {
                $list->filterByAttribute($this->attributeKey->getAttributeKeyHandle(), $value, '=');
            }
        }

        return $list;
    }

    /**
     *
     */
    public function type_form()
    {
        $this->load();
        $hhh = Loader::helper('https_handling', 'handle_https');
        /* @var $hhh HttpsHandlingHelper */
        $this->set('akValidDefaultRequirements', $hhh->getHandlings());
        $this->set('akEnabled', $this->akEnabled);
        $this->set('akRedirectEditors', $this->akRedirectEditors);
        $this->set('akDefaultRequirement', $this->akDefaultRequirement);
        $this->set('akCustomDomains', $this->akCustomDomains);
        $this->set('akHTTPDomain', $this->akHTTPDomain);
        $this->set('akHTTPSDomain', $this->akHTTPSDomain);
    }

    /**
     *
     */
    public function form()
    {
        $hhh = Loader::helper('https_handling', 'handle_https');
        $this->load();
        /* @var $hhh HttpsHandlingHelper */
        $value = '';
        $values = $hhh->getHandlings();
        if (is_object($this->attributeValue)) {
            $value = $this->getAttributeValue()->getValue();
            if (!(is_string($value) && array_key_exists($value, $values))) {
                $value = '';
            }
        }
        $fh = Loader::helper('form');
        /* @var $hhh FormHelper */
        echo $fh->select($this->field('value'), array_merge(array('' => t('Use default settings')), $values), $value);
    }

    /**
     * @param array $data
     */
    public function saveForm($data)
    {
        $this->saveValue((is_array($data) && array_key_exists('value', $data)) ? $data['value'] : '');
    }

    /**
     * @return boolean
     */
    protected function load()
    {
        $result = false;
        $hhh = Loader::helper('https_handling', 'handle_https');
        /* @var $hhh HttpsHandlingHelper */
        $this->akEnabled = 0;
        $this->akRedirectEditors = 0;
        $this->akDefaultRequirement = $hhh::SSLHANDLING_DOESNOT_MATTER;
        $this->akCustomDomains = 0;
        $this->akHTTPDomain = '';
        $this->akHTTPSDomain = '';
        $ak = $this->getAttributeKey();
        if (is_object($ak)) {
            $row = Loader::db()->GetRow('select akEnabled, akRedirectEditors, akDefaultRequirement, akCustomDomains, akHTTPDomain, akHTTPSDomain from atHandleHttpsConfig where akID = ?', $ak->getAttributeKeyID());
            if ($row) {
                $this->akEnabled = empty($row['akEnabled']) ? 0 : 1;
                $this->akRedirectEditors = empty($row['akRedirectEditors']) ? 0 : 1;
                if (array_key_exists($row['akDefaultRequirement'], $hhh->getHandlings())) {
                    $this->akDefaultRequirement = $hhh::SSLHANDLING_DOESNOT_MATTER;
                }
                $this->akCustomDomains = empty($row['akCustomDomains']) ? 0 : 1;
                $this->akHTTPDomain = is_string($row['akHTTPDomain']) ? $row['akHTTPDomain'] : '';
                $this->akHTTPSDomain = is_string($row['akHTTPSDomain']) ? $row['akHTTPSDomain'] : '';
                $result = true;
            }
        }

        return $result;
    }

}
