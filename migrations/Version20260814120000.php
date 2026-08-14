<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260814120000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Upgrade label sheets from output modes and pitch to units, page sizes, gutters, and corner radius.';
    }

    private function addCommonColumns(): void
    {
        $this->addSql("ALTER TABLE label_profiles ADD options_unit VARCHAR(255) DEFAULT 'mm' NOT NULL");
        $this->addSql("ALTER TABLE label_profiles ADD options_page_size VARCHAR(255) DEFAULT 'CUSTOM' NOT NULL");
        $this->addSql('ALTER TABLE label_profiles ADD options_sticker_corner_radius DOUBLE PRECISION DEFAULT 0 NOT NULL');
    }

    private function removeCommonColumns(): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_unit');
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_page_size');
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_sticker_corner_radius');
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addCommonColumns();
        $this->addSql('ALTER TABLE label_profiles CHANGE options_sheet_horizontal_pitch options_sheet_gutter_width DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE label_profiles CHANGE options_sheet_vertical_pitch options_sheet_gutter_height DOUBLE PRECISION NOT NULL');
        $this->addSql('UPDATE label_profiles SET options_sheet_gutter_width = GREATEST(options_sheet_gutter_width - options_width, 0), options_sheet_gutter_height = GREATEST(options_sheet_gutter_height - options_height, 0)');
        $this->normalizeSingleLabels();
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_output_mode');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->restoreOutputMode();
        $this->addSql('UPDATE label_profiles SET options_sheet_gutter_width = options_sheet_gutter_width + options_width, options_sheet_gutter_height = options_sheet_gutter_height + options_height');
        $this->addSql('ALTER TABLE label_profiles CHANGE options_sheet_gutter_width options_sheet_horizontal_pitch DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE label_profiles CHANGE options_sheet_gutter_height options_sheet_vertical_pitch DOUBLE PRECISION NOT NULL');
        $this->removeCommonColumns();
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addCommonColumns();
        $this->addSql('ALTER TABLE label_profiles RENAME COLUMN options_sheet_horizontal_pitch TO options_sheet_gutter_width');
        $this->addSql('ALTER TABLE label_profiles RENAME COLUMN options_sheet_vertical_pitch TO options_sheet_gutter_height');
        $this->addSql('UPDATE label_profiles SET options_sheet_gutter_width = MAX(options_sheet_gutter_width - options_width, 0), options_sheet_gutter_height = MAX(options_sheet_gutter_height - options_height, 0)');
        $this->normalizeSingleLabels();
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_output_mode');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->restoreOutputMode();
        $this->addSql('UPDATE label_profiles SET options_sheet_gutter_width = options_sheet_gutter_width + options_width, options_sheet_gutter_height = options_sheet_gutter_height + options_height');
        $this->addSql('ALTER TABLE label_profiles RENAME COLUMN options_sheet_gutter_width TO options_sheet_horizontal_pitch');
        $this->addSql('ALTER TABLE label_profiles RENAME COLUMN options_sheet_gutter_height TO options_sheet_vertical_pitch');
        $this->removeCommonColumns();
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addCommonColumns();
        $this->addSql('ALTER TABLE label_profiles RENAME COLUMN options_sheet_horizontal_pitch TO options_sheet_gutter_width');
        $this->addSql('ALTER TABLE label_profiles RENAME COLUMN options_sheet_vertical_pitch TO options_sheet_gutter_height');
        $this->addSql('UPDATE label_profiles SET options_sheet_gutter_width = GREATEST(options_sheet_gutter_width - options_width, 0), options_sheet_gutter_height = GREATEST(options_sheet_gutter_height - options_height, 0)');
        $this->normalizeSingleLabels();
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_output_mode');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->restoreOutputMode();
        $this->addSql('UPDATE label_profiles SET options_sheet_gutter_width = options_sheet_gutter_width + options_width, options_sheet_gutter_height = options_sheet_gutter_height + options_height');
        $this->addSql('ALTER TABLE label_profiles RENAME COLUMN options_sheet_gutter_width TO options_sheet_horizontal_pitch');
        $this->addSql('ALTER TABLE label_profiles RENAME COLUMN options_sheet_gutter_height TO options_sheet_vertical_pitch');
        $this->removeCommonColumns();
    }

    private function normalizeSingleLabels(): void
    {
        $this->addSql("UPDATE label_profiles SET options_sheet_width = options_width, options_sheet_height = options_height, options_sheet_margin_left = 0, options_sheet_margin_top = 0, options_sheet_gutter_width = 0, options_sheet_gutter_height = 0, options_sheet_columns = 1, options_sheet_rows = 1 WHERE options_output_mode = 'single_label'");
    }

    private function restoreOutputMode(): void
    {
        $this->addSql("ALTER TABLE label_profiles ADD options_output_mode VARCHAR(255) DEFAULT 'single_label' NOT NULL");
        $this->addSql("UPDATE label_profiles SET options_output_mode = 'label_sheet' WHERE options_sheet_columns > 1 OR options_sheet_rows > 1");
    }
}
