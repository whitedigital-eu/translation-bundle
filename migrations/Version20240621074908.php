<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240621074908 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add translation tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE lexik_trans_unit (id SERIAL NOT NULL, key_name VARCHAR(255) NOT NULL, domain VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_deleted BOOLEAN DEFAULT false, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX key_domain_idx ON lexik_trans_unit (key_name, domain)');
        $this->addSql('CREATE TABLE lexik_trans_unit_translations (id SERIAL NOT NULL, file_id INT DEFAULT NULL, trans_unit_id INT DEFAULT NULL, locale VARCHAR(10) NOT NULL, content TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_manually BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B0AA394493CB796C ON lexik_trans_unit_translations (file_id)');
        $this->addSql('CREATE INDEX IDX_B0AA3944C3C583C9 ON lexik_trans_unit_translations (trans_unit_id)');
        $this->addSql('CREATE UNIQUE INDEX trans_unit_locale_idx ON lexik_trans_unit_translations (trans_unit_id, locale)');
        $this->addSql('CREATE TABLE lexik_translation_file (id SERIAL NOT NULL, domain VARCHAR(255) NOT NULL, locale VARCHAR(10) NOT NULL, extention VARCHAR(10) NOT NULL, path VARCHAR(255) NOT NULL, hash VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX hash_idx ON lexik_translation_file (hash)');
        $this->addSql('CREATE TABLE IF NOT EXISTS translation (id SERIAL NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, domain VARCHAR(255) NOT NULL, locale VARCHAR(255) NOT NULL, key VARCHAR(255) NOT NULL, translation TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_B469456FA7A91E0B4180C6988A90ABA9B469456F ON translation (domain, locale, key, translation)');
        $this->addSql('COMMENT ON COLUMN translation.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN translation.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE lexik_trans_unit_translations ADD CONSTRAINT FK_B0AA394493CB796C FOREIGN KEY (file_id) REFERENCES lexik_translation_file (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE lexik_trans_unit_translations ADD CONSTRAINT FK_B0AA3944C3C583C9 FOREIGN KEY (trans_unit_id) REFERENCES lexik_trans_unit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lexik_trans_unit_translations DROP CONSTRAINT FK_B0AA394493CB796C');
        $this->addSql('ALTER TABLE lexik_trans_unit_translations DROP CONSTRAINT FK_B0AA3944C3C583C9');
        $this->addSql('DROP TABLE lexik_trans_unit');
        $this->addSql('DROP TABLE lexik_trans_unit_translations');
        $this->addSql('DROP TABLE lexik_translation_file');
        $this->addSql('DROP TABLE translation');
    }
}
