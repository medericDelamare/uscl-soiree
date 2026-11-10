<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Length;

/** @extends AbstractCrudController<User> */
final class UserCrudController extends AbstractCrudController
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Compte')->setEntityLabelInPlural('Comptes');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield EmailField::new('email', 'E-mail');
        yield BooleanField::new('admin', 'Administrateur')->setHelp('Sinon : contrôle des billets à l\'entrée uniquement.');
        yield TextField::new('plainPassword', 'Mot de passe')
            ->onlyOnForms()
            ->setFormType(PasswordType::class)
            ->setFormTypeOptions(['mapped' => false, 'constraints' => [new Length(min: 8)], 'attr' => ['autocomplete' => 'new-password']])
            ->setRequired(Crud::PAGE_NEW === $pageName)
            ->setHelp(Crud::PAGE_EDIT === $pageName ? 'Laisser vide pour ne pas changer.' : '8 caractères minimum.');
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPassword($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPassword($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashPassword(User $user): void
    {
        $plain = $this->getContext()?->getRequest()->request->all()['User']['plainPassword'] ?? null;
        if ($plain) {
            $user->setPassword($this->hasher->hashPassword($user, $plain));
        }
    }
}
