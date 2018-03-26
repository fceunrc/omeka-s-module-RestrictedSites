<?php
namespace RestrictedSites\Controller\Site;
use Omeka\Form\LoginForm;
use Zend\Authentication\AuthenticationService;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Session\Container;
use Zend\View\Model\ViewModel;

/**
 * Provides controller sitelogin and action login for managing acess to sites
 * marked as restricted to a limited user list
 *
 * @author laurent
 *        
 */
class SiteLoginController extends AbstractActionController
{

    /**
     *
     * @var AuthenticationService
     */
    protected $auth;

    /**
     * Data required by the factory to instantiate controller
     *
     * @param EntityManager $entityManager            
     * @param AuthenticationService $auth            
     */
    public function __construct (AuthenticationService $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Unique login action to display Login form and handle login procedure.
     * Returns with "Forbidden" code 403 for non-authorized users.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function loginAction ()
    {
        /** @var \Omeka\Api\Representation\SiteRepresentation $site */
        $site = $this->currentSite();
        $siteSlug = $site->slug();
        // TODO: handle access to non existing site
        
        if ($this->auth->hasIdentity()) {
            
            $userId = $this->auth->getIdentity()->getId();
            $sitePermissions = $site->sitePermissions();
            foreach ($sitePermissions as $sitePermission) {
                /** @var \Omeka\Api\Representation\UserRepresentation $registeredUser */
                $registeredUser = $sitePermission->user();
                $registeredUserId = $registeredUser->id();
                if ($registeredUserId == $userId)
                    // Authorized user, redirecting to site.
                    return $this->redirect()->toRoute('site', 
                            array(
                                    'site-slug' => $siteSlug
                            ));
            }
            // Non authorized user, sending Forbidden error code
            $this->response->setStatusCode(403);
            $this->messenger()->addError('Forbidden'); // @translate
        }
        
        // Anonymous user, display and handle login form
        /** @var Omeka\Form\LoginForm $form */
        $form = $this->getForm(LoginForm::class);
        
        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost();
            $form->setData($data);
            if ($form->isValid()) {
                $sessionManager = Container::getDefaultManager();
                $sessionManager->regenerateId();
                $validatedData = $form->getData();
                $adapter = $this->auth->getAdapter();
                $adapter->setIdentity($validatedData['email']);
                $adapter->setCredential($validatedData['password']);
                $result = $this->auth->authenticate();
                if ($result->isValid()) {
                    // $this->messenger()->addSuccess('Successfully logged in');
                    // // @translate
                    $session = $sessionManager->getStorage();
                    if ($redirectUrl = $session->offsetGet('redirect_url')) {
                        return $this->redirect()->toUrl($redirectUrl);
                    }
                    return $this->redirect()->toRoute('site');
                } else {
                    $this->messenger()->addError('Email or password is invalid'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        
        /** @var \Zend\View\Model\ViewModel $view */
        $view = new ViewModel();
        $view->setVariable('form', $form);
        $view->setVariable('site', $site);
        $view->setVariable('isLogin', true); // This variable is used to hide
                                             // specific content on the login
                                             // form (e.g. Search or Navigation
                                             // menus).
        return $view;
    }
}