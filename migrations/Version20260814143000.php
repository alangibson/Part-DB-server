<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260814143000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Move reusable, named label sheet layouts into their own table and reference them from label profiles.';
    }

    private function copySheets(): void
    {
        $this->addSql("INSERT INTO label_sheets (id, name, unit, page_size, width, height, margin_left, margin_top, gutter_width, gutter_height, columns, rows, corner_radius, last_modified, datetime_added) SELECT id, name || ' Sheet', options_unit, options_page_size, options_sheet_width, options_sheet_height, options_sheet_margin_left, options_sheet_margin_top, options_sheet_gutter_width, options_sheet_gutter_height, options_sheet_columns, options_sheet_rows, options_sticker_corner_radius, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM label_profiles");
        $this->addSql('UPDATE label_profiles SET label_sheet_id = id');
    }

    private function dropOldColumns(): void
    {
        foreach (['options_unit', 'options_page_size', 'options_sheet_width', 'options_sheet_height', 'options_sheet_margin_left', 'options_sheet_margin_top', 'options_sheet_gutter_width', 'options_sheet_gutter_height', 'options_sheet_columns', 'options_sheet_rows', 'options_sticker_corner_radius'] as $column) {
            $this->addSql('ALTER TABLE label_profiles DROP COLUMN '.$column);
        }
    }

    private function restoreOldColumns(): void
    {
        $this->addSql("ALTER TABLE label_profiles ADD options_unit VARCHAR(255) DEFAULT 'mm' NOT NULL");
        $this->addSql("ALTER TABLE label_profiles ADD options_page_size VARCHAR(255) DEFAULT 'A4' NOT NULL");
        foreach (['options_sheet_width', 'options_sheet_height', 'options_sheet_margin_left', 'options_sheet_margin_top', 'options_sheet_gutter_width', 'options_sheet_gutter_height', 'options_sticker_corner_radius'] as $column) {
            $this->addSql('ALTER TABLE label_profiles ADD '.$column.' DOUBLE PRECISION DEFAULT 0 NOT NULL');
        }
        $this->addSql('ALTER TABLE label_profiles ADD options_sheet_columns INTEGER DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD options_sheet_rows INTEGER DEFAULT 1 NOT NULL');
        $this->addSql('UPDATE label_profiles SET options_unit = (SELECT unit FROM label_sheets WHERE id = label_sheet_id), options_page_size = (SELECT page_size FROM label_sheets WHERE id = label_sheet_id), options_sheet_width = (SELECT width FROM label_sheets WHERE id = label_sheet_id), options_sheet_height = (SELECT height FROM label_sheets WHERE id = label_sheet_id), options_sheet_margin_left = (SELECT margin_left FROM label_sheets WHERE id = label_sheet_id), options_sheet_margin_top = (SELECT margin_top FROM label_sheets WHERE id = label_sheet_id), options_sheet_gutter_width = (SELECT gutter_width FROM label_sheets WHERE id = label_sheet_id), options_sheet_gutter_height = (SELECT gutter_height FROM label_sheets WHERE id = label_sheet_id), options_sheet_columns = (SELECT columns FROM label_sheets WHERE id = label_sheet_id), options_sheet_rows = (SELECT rows FROM label_sheets WHERE id = label_sheet_id), options_sticker_corner_radius = (SELECT corner_radius FROM label_sheets WHERE id = label_sheet_id)');
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('CREATE TABLE label_sheets (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, unit VARCHAR(255) NOT NULL, page_size VARCHAR(255) NOT NULL, width DOUBLE PRECISION NOT NULL, height DOUBLE PRECISION NOT NULL, margin_left DOUBLE PRECISION NOT NULL, margin_top DOUBLE PRECISION NOT NULL, gutter_width DOUBLE PRECISION NOT NULL, gutter_height DOUBLE PRECISION NOT NULL, columns INT NOT NULL, rows INT NOT NULL, corner_radius DOUBLE PRECISION NOT NULL, last_modified DATETIME NOT NULL, datetime_added DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE label_profiles ADD label_sheet_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_C93E9CF5C91C1F60 ON label_profiles (label_sheet_id)');
        $this->addSql("INSERT INTO label_sheets (id, name, unit, page_size, width, height, margin_left, margin_top, gutter_width, gutter_height, columns, rows, corner_radius, last_modified, datetime_added) SELECT id, CONCAT(name, ' Sheet'), options_unit, options_page_size, options_sheet_width, options_sheet_height, options_sheet_margin_left, options_sheet_margin_top, options_sheet_gutter_width, options_sheet_gutter_height, options_sheet_columns, options_sheet_rows, options_sticker_corner_radius, NOW(), NOW() FROM label_profiles");
        $this->addSql('UPDATE label_profiles SET label_sheet_id = id');
        $this->addSql('ALTER TABLE label_profiles ADD CONSTRAINT FK_LABEL_PROFILE_SHEET FOREIGN KEY (label_sheet_id) REFERENCES label_sheets (id)');
        $this->dropOldColumns();
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('CREATE TABLE label_sheets (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, unit VARCHAR(255) NOT NULL, page_size VARCHAR(255) NOT NULL, width DOUBLE PRECISION NOT NULL, height DOUBLE PRECISION NOT NULL, margin_left DOUBLE PRECISION NOT NULL, margin_top DOUBLE PRECISION NOT NULL, gutter_width DOUBLE PRECISION NOT NULL, gutter_height DOUBLE PRECISION NOT NULL, columns INTEGER NOT NULL, rows INTEGER NOT NULL, corner_radius DOUBLE PRECISION NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('ALTER TABLE label_profiles ADD label_sheet_id INTEGER DEFAULT NULL REFERENCES label_sheets (id) ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_C93E9CF5C91C1F60 ON label_profiles (label_sheet_id)');
        $this->copySheets();
        $this->dropOldColumns();
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('CREATE TABLE label_sheets (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, unit VARCHAR(255) NOT NULL, page_size VARCHAR(255) NOT NULL, width DOUBLE PRECISION NOT NULL, height DOUBLE PRECISION NOT NULL, margin_left DOUBLE PRECISION NOT NULL, margin_top DOUBLE PRECISION NOT NULL, gutter_width DOUBLE PRECISION NOT NULL, gutter_height DOUBLE PRECISION NOT NULL, columns INT NOT NULL, rows INT NOT NULL, corner_radius DOUBLE PRECISION NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE label_profiles ADD label_sheet_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_C93E9CF5C91C1F60 ON label_profiles (label_sheet_id)');
        $this->copySheets();
        $this->addSql('ALTER TABLE label_profiles ADD CONSTRAINT FK_LABEL_PROFILE_SHEET FOREIGN KEY (label_sheet_id) REFERENCES label_sheets (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->dropOldColumns();
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->restoreOldColumns();
        $this->addSql('ALTER TABLE label_profiles DROP FOREIGN KEY FK_LABEL_PROFILE_SHEET');
        $this->addSql('DROP INDEX IDX_C93E9CF5C91C1F60 ON label_profiles');
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN label_sheet_id');
        $this->addSql('DROP TABLE label_sheets');
    }

    public function sqLiteDown(Schema $schema): void { $this->restoreEmbeddedSheets(); }
    public function postgreSQLDown(Schema $schema): void
    {
        $this->restoreOldColumns();
        $this->addSql('ALTER TABLE label_profiles DROP CONSTRAINT FK_LABEL_PROFILE_SHEET');
        $this->addSql('DROP INDEX IDX_C93E9CF5C91C1F60');
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN label_sheet_id');
        $this->addSql('DROP TABLE label_sheets');
    }

    private function restoreEmbeddedSheets(): void
    {
        $this->restoreOldColumns();
        $this->addSql('DROP INDEX IDX_C93E9CF5C91C1F60');
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN label_sheet_id');
        $this->addSql('DROP TABLE label_sheets');
    }
}
