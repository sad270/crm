<?php

namespace Oro\Bundle\SalesBundle\Form\Extension;

use Oro\Bundle\AccountBundle\Form\Type\AccountSelectType;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\SalesBundle\Entity\Customer;
use Oro\Bundle\SalesBundle\Entity\Manager\AccountCustomerManager;
use Oro\Bundle\SalesBundle\Provider\Customer\ConfigProvider;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Adds associated account field for customers form.
 */
class CustomerAssociationAccountExtension extends AbstractTypeExtension
{
    /** @var ConfigProvider */
    protected $customerConfigProvider;

    /** @var AccountCustomerManager */
    protected $manager;

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /**
     * @param ConfigProvider         $customerConfigProvider
     * @param AccountCustomerManager $manager
     * @param DoctrineHelper         $doctrineHelper
     */
    public function __construct(
        ConfigProvider $customerConfigProvider,
        AccountCustomerManager $manager,
        DoctrineHelper $doctrineHelper
    ) {
        $this->customerConfigProvider = $customerConfigProvider;
        $this->manager                = $manager;
        $this->doctrineHelper         = $doctrineHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['customer_association_disabled']) {
            return;
        }

        $formConfig = $builder->getFormConfig();

        if (!$formConfig->getCompound()) {
            return;
        }

        $dataClassName = $formConfig->getDataClass();
        if (!$dataClassName || !$this->customerConfigProvider->isCustomerClass($dataClassName)) {
            return;
        }

        $builder->add(
            'customer_association_account',
            AccountSelectType::class,
            [
                'required' => $this->isAccountRequired($builder->getData()),
                'label'    => 'oro.account.entity_label',
                'mapped'   => false,
            ]
        );

        $builder->addEventListener(
            FormEvents::POST_SET_DATA,
            function (FormEvent $event) {
                $target = $event->getData();
                if (!$target || $this->doctrineHelper->isNewEntity($target)) {
                    return;
                }

                $customer = $this->manager->getAccountCustomerByTarget($target, false);
                if ($customer) {
                    $event->getForm()->get('customer_association_account')->setData($customer->getAccount());
                }
            }
        );
        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event) {
                $this->setAccountForCustomer($event);
            }
        );
    }

    /**
     * @param Customer|null $target
     * @return bool
     */
    private function isAccountRequired($target)
    {
        return $target !== null && !$this->doctrineHelper->isNewEntity($target);
    }

    /**
     * @param FormEvent $event
     */
    private function setAccountForCustomer(FormEvent $event)
    {
        $target  = $event->getData();
        $account = $event->getForm()->get('customer_association_account')->getData();
        if ($this->doctrineHelper->isNewEntity($target)) {
            $account = $account ?? $this->manager->createAccountForTarget($target);
            $customer = AccountCustomerManager::createCustomer($account, $target);
            $this->doctrineHelper->getEntityManager($customer)->persist($customer);

            return;
        }

        if (!$account) {
            return;
        }

        $customer = $this->manager->getAccountCustomerByTarget($target, false);
        if ($customer) {
            $customer->setTarget($account, $target);
        } else {
            $customer = AccountCustomerManager::createCustomer($account, $target);
            $this->doctrineHelper->getEntityManager($customer)->persist($customer);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'customer_association_disabled' => false
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return 'Symfony\Component\Form\Extension\Core\Type\FormType';
    }
}
