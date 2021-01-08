<?php

namespace Bleez\CentralAlunoLogin\Plugin;

use Magento\Framework\UrlFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Bleez\CentralAlunoLogin\Model\CentralAlunoLoginFactory;

/**
 * Class BeforeRegisterPlugin
 * @package Bleez\CentralAlunoLogin\Plugin
 */
class BeforeRegisterPlugin
{
    const INTEGRACAO_ATIVA = 'customer/configurations/enable_integration';
    
    /** @var \Magento\Framework\UrlInterface */
    protected $urlModel;

    /** * @var \Magento\Framework\Controller\Result\RedirectFactory */
    protected $resultRedirectFactory;

    /** * @var \Magento\Framework\Message\ManagerInterface */
    protected $messageManager;

    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    /** @var CentralAlunoLoginFactory */
    protected $centralAlunoLoginFactory;

    /**
     * BeforeRegisterPlugin constructor.
     * @param UrlFactory $urlFactory
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     * @param ScopeConfigInterface $scopeConfig
     * @param CentralAlunoLoginFactory $centralAlunoLoginFactory
     */
    public function __construct(
        UrlFactory $urlFactory,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager,
        ScopeConfigInterface $scopeConfig,
        CentralAlunoLoginFactory $centralAlunoLoginFactory
    )
    {
        $this->urlModel = $urlFactory->create();
        $this->scopeConfig = $scopeConfig;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $redirectFactory;
        $this->centralAlunoLoginFactory = $centralAlunoLoginFactory;
    }

    /**
     * @param \Magento\Customer\Controller\Account\CreatePost $subject
     * @param \Closure $proceed
     * @return mixed
     */
    public function aroundExecute(
        \Magento\Customer\Controller\Account\CreatePost $subject,
        \Closure $proceed
    ) {
        if ($this->getIntegracaoAtiva()) {
            $cpf = $subject->getRequest()->getParam('taxvat');

            $buscarResp = $this->buscarResp($cpf);

            if(!$buscarResp) {
                $this->messageManager->addErrorMessage(
                    'CPF Não Cadastrado em Nosso Sistema'
                );
                $defaultUrl = $this->urlModel->getUrl('*/*/create', ['_secure' => true]);
                /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();

                return $resultRedirect->setUrl($defaultUrl);
            }
        }

        return $proceed();
    }

    /**
     * @return mixed
     */
    private function getIntegracaoAtiva()
    {
        return $this->scopeConfig->getValue(self::INTEGRACAO_ATIVA);
    }

    /**
     * @param string $cpf
     * @return \Magento\Framework\Data\Collection\AbstractDb|\Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection|null
     */
    private function buscarResp(string $cpf)
    {
        $cpfFormated = $this->formatterCPFCNPJToErp($cpf);

        $centralAlunoLogin = $this->centralAlunoLoginFactory->create();

        $dados = $centralAlunoLogin->getCollection()->addFieldToFilter('CPF_Resp_Legal', $cpfFormated)->load();

        if ($dados->getFirstItem()->getData()){
            return $dados;
        }

        return null;
    }

    /**
     * @param string $cpfOrCnpj
     * @return string|string[]|null
     */
    private function formatterCPFCNPJToErp(string $cpfOrCnpj)
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpfOrCnpj);
        return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "\$1\$2\$3-\$4", $cpf);
    }

}
