<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Spmatrix extends MY_Controller
{
    /**
     * @var $tmp_providers \models\Provider[]
     */
    private $tmp_providers;

    public function __construct()
    {
        parent::__construct();
        $this->tmp_providers = new models\Providers;
    }

    public function show($id = null)
    {
        $loggedin = $this->jauth->isLoggedIn();
        if (!$loggedin) {
            redirect('auth/login', 'location');
        }
        if (empty($id) || !ctype_digit($id)) {
            show_404();
        }
        /**
         * @var $sp models\Provider
         */
        $sp = $this->em->getRepository("models\Provider")->findOneBy(array('id' => $id));
        if ($sp === null) {
            show_404();
        }


        $this->load->library('zacl');
        $hasWriteAccess = $this->zacl->check_acl($sp->getId(), 'write', 'entity', '');
        if (!$hasWriteAccess) {
            show_error('Permission denied', 403);
        }
        $myLang = MY_Controller::getLang();
        $titlename = $sp->getNameToWebInLang($myLang, $sp->getType());
        $this->title = $titlename;

        $data = array(
            'titlepage' => '<a href="' . base_url() . 'providers/detail/show/' . $sp->getId() . '">' .$titlename . '</a>',
            'subtitlepage' =>  lang('rr_provideingattrsoverview'),
            'content_view' => 'reports/spmatrix_view',
            'spid' => $sp->getId(),
            'breadcrumbs' => array(
                array('url' => base_url('providers/sp_list/showlist'), 'name' => lang('serviceproviders')),
                array('url' => base_url('providers/detail/show/' . $sp->getId() . ''), 'name' => '' . html_escape($titlename) . ''),
                array('url' => '#', 'name' => lang('rr_arpoverview'), 'type' => 'current'),
            )
        );
        $this->load->view(MY_Controller::$page, $data);

    }

    public function getdiag($id = null)
    {
        $loggedin = $this->jauth->isLoggedIn();
        $isAjax = $this->input->is_ajax_request();
        if (!$loggedin || !$isAjax) {
            return $this->output->set_status_header(403)->set_output('no permission');
        }
        if (empty($id) || !ctype_digit($id)) {
            return $this->output->set_status_header(404)->set_output('not found');
        }
        /**
         * @var $sp models\Provider
         */
        $sp = $this->em->getRepository("models\Provider")->findOneBy(array('id' => $id));
        if ($sp === null) {
            return $this->output->set_status_header(404)->set_output('not found');
        }

        $spEntityId = $sp->getEntityId();
        $this->load->library('zacl');
        $hasWriteAccess = $this->zacl->check_acl($sp->getId(), 'write', 'entity', '');
        if (!$hasWriteAccess) {
            return $this->output->set_status_header(403)->set_output('Permission Denied');
        }
        $myLang = MY_Controller::getLang();
        $titlename = $sp->getNameToWebInLang($myLang, $sp->getType());
        $this->title = $titlename;
        $result['providerprefurl'] = base_url('providers/detail/show/');
        $result['data'] = array();
        /**
         * @var $members models\Provider[]
         */
        $members = $this->tmp_providers->getCircleMembersIDP($sp, NULL, TRUE);

        $this->load->library('arp_generator');

        $ok = false;
        foreach ($members as $member) {
            $entityID = $member->getEntityId();
            $result1 = $this->arp_generator->arpToXML($member, true);
            if (is_array($result1) && array_key_exists($spEntityId, $result1)) {
                if (!$ok) {
                    $result['attrs'] = $result1['' . $spEntityId . '']['req'];
                    $ok = true;
                }
                $result['data'][] = array('idpid' => $member->getId(), 'name' => $member->getNameToWebInLang($myLang, 'idp'), 'entityid' => $entityID, 'data' => $result1['' . $spEntityId . '']);

            }

        }
        if (count($result['data']) == 0) {
            $result['message'] = 'No policies found';
        }
        $this->output->set_content_type('application/json')->set_output(json_encode($result));
    }

}

