<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240730072709 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'rework isDeleted in TransUnit';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE lexik_trans_unit_is_deleted (id SERIAL NOT NULL, trans_unit_id INT DEFAULT NULL, is_deleted BOOLEAN DEFAULT false NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3E4923C5C3C583C9 ON lexik_trans_unit_is_deleted (trans_unit_id)');
        $this->addSql('CREATE INDEX IDX_3E4923C5FD07C8FB ON lexik_trans_unit_is_deleted (is_deleted)');
        $this->addSql('CREATE INDEX IDX_3E4923C5C3C583C9FD07C8FB ON lexik_trans_unit_is_deleted (trans_unit_id, is_deleted)');
        $this->addSql('ALTER TABLE lexik_trans_unit_is_deleted ADD CONSTRAINT FK_3E4923C5C3C583C9 FOREIGN KEY (trans_unit_id) REFERENCES lexik_trans_unit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX IF EXISTS idx_trans_unit_is_deleted');
        $this->addSql('ALTER TABLE lexik_trans_unit DROP is_deleted');
        $this->addSql('DROP INDEX IF EXISTS idx_translations_locale');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lexik_trans_unit_is_deleted DROP CONSTRAINT FK_3E4923C5C3C583C9');
        $this->addSql('DROP TABLE lexik_trans_unit_is_deleted');
        $this->addSql('CREATE INDEX idx_translations_locale ON lexik_trans_unit_translations (locale)');
        $this->addSql('ALTER TABLE lexik_trans_unit ADD is_deleted BOOLEAN DEFAULT false');
        $this->addSql('CREATE INDEX idx_trans_unit_is_deleted ON lexik_trans_unit (is_deleted)');
        $this->addSql('
            DO $$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_class WHERE relname = \'idx_translations_locale\') THEN
                    CREATE INDEX idx_translations_locale ON lexik_trans_unit_translations (locale);
                END IF;
            END $$;',
        );
    }
}
