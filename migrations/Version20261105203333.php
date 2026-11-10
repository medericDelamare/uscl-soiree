<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261105203333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE booking (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(20) NOT NULL, access_token VARCHAR(32) NOT NULL, first_name VARCHAR(80) NOT NULL, last_name VARCHAR(80) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(30) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, paid_at DATETIME DEFAULT NULL, payment_provider VARCHAR(20) DEFAULT NULL, payment_reference VARCHAR(80) DEFAULT NULL, payment_intents JSON NOT NULL, UNIQUE INDEX UNIQ_E00CEDDEAEA34913 (reference), UNIQUE INDEX UNIQ_E00CEDDEB6A2DD68 (access_token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE guest (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(80) NOT NULL, last_name VARCHAR(80) NOT NULL, age SMALLINT NOT NULL, meal VARCHAR(20) NOT NULL, price INT NOT NULL, checked_in_at DATETIME DEFAULT NULL, booking_id INT NOT NULL, INDEX IDX_ACB79A353301C60 (booking_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, name VARCHAR(120) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE guest ADD CONSTRAINT FK_ACB79A353301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE guest DROP FOREIGN KEY FK_ACB79A353301C60');
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE guest');
        $this->addSql('DROP TABLE `user`');
    }
}
