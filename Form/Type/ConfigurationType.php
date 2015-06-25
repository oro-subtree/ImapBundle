<?php

namespace Oro\Bundle\ImapBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use Oro\Bundle\ImapBundle\Entity\ImapEmailOrigin;
use Oro\Bundle\SecurityBundle\Encoder\Mcrypt;
use Oro\Bundle\SecurityBundle\SecurityFacade;

class ConfigurationType extends AbstractType
{
    const NAME = 'oro_imap_configuration';

    /** @var Mcrypt */
    protected $encryptor;

    /** @var SecurityFacade */
    protected $securityFacade;

    public function __construct(Mcrypt $encryptor, SecurityFacade $securityFacade)
    {
        $this->encryptor = $encryptor;
        $this->securityFacade = $securityFacade;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // pre-populate password, imap origin change
        $this->addPrepopulatePasswordEventListener($builder);
        $this->addOwnerOrganizationEventListener($builder);

        $builder
            ->add(
                'host',
                'text',
                array('label' => 'oro.imap.configuration.host.label', 'required' => true)
            )
            ->add(
                'port',
                'number',
                array('label' => 'oro.imap.configuration.port.label', 'required' => true)
            )
            ->add(
                'ssl',
                'choice',
                array(
                    'label'       => 'oro.imap.configuration.ssl.label',
                    'choices'     => array('ssl' => 'SSL', 'tls' => 'TLS'),
                    'empty_data'  => null,
                    'empty_value' => '',
                    'required'    => false
                )
            )
            ->add(
                'user',
                'text',
                array('label' => 'oro.imap.configuration.user.label', 'required' => true)
            )
            ->add(
                'password',
                'password',
                array('label' => 'oro.imap.configuration.password.label', 'required' => true)
            )
            ->add('check_connection', new CheckButtonType());
    }

    protected function addPrepopulatePasswordEventListener(FormBuilderInterface $builder)
    {
        $encryptor = $this->encryptor;

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($encryptor) {
                $data = (array) $event->getData();
                /** @var ImapEmailOrigin|null $entity */
                $entity = $event->getForm()->getData();

                $filtered = array_filter(
                    $data,
                    function ($item) {
                        return !empty($item);
                    }
                );

                if (!empty($filtered)) {
                    $oldPassword = $event->getForm()->get('password')->getData();
                    if (empty($data['password']) && $oldPassword) {
                        // populate old password
                        $data['password'] = $oldPassword;
                    } else {
                        $data['password'] = $encryptor->encryptData($data['password']);
                    }

                    $event->setData($data);

                    if ($entity instanceof ImapEmailOrigin
                        && ($entity->getHost() != $data['host'] || $entity->getUser() != $data['user'])
                    ) {
                        // in case when critical fields were changed new entity should be created
                        $newConfiguration = new ImapEmailOrigin();
                        $event->getForm()->setData($newConfiguration);
                    }
                } elseif ($entity instanceof ImapEmailOrigin) {
                    $event->getForm()->setData(null);
                }
            }
        );
    }

    /**
     * @param FormBuilderInterface $builder
     */
    protected function addOwnerOrganizationEventListener(FormBuilderInterface $builder)
    {
        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var ImapEmailOrigin $data */
                $data = $event->getData();
                if ($data !== null) {
                    if ($data->getOwner() === null) {
                        $data->setOwner($this->securityFacade->getLoggedUser());
                    }
                    if ($data->getOrganization() === null) {
                        $organization = $this->securityFacade->getOrganization()
                            ? $this->securityFacade->getOrganization()
                            : $this->securityFacade->getLoggedUser()->getOrganization();
                        $data->setOrganization($organization);
                    }
                    $event->setData($data);
                }
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => 'Oro\\Bundle\\ImapBundle\\Entity\\ImapEmailOrigin',
                'required'   => false
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }
}
