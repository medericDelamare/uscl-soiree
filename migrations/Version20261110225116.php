<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261110225116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cartons d\'invitation numérotés (un par personne)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ticket (id INT AUTO_INCREMENT NOT NULL, number INT NOT NULL, token VARCHAR(32) NOT NULL, activated_at DATETIME DEFAULT NULL, guest_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_97A0ADA396901F54 (number), UNIQUE INDEX UNIQ_97A0ADA35F37A13B (token), UNIQUE INDEX UNIQ_97A0ADA39A4AA658 (guest_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA39A4AA658 FOREIGN KEY (guest_id) REFERENCES guest (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA39A4AA658');
        $this->addSql('DROP TABLE ticket');
    }
}
