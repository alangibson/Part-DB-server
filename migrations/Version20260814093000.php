<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260814093000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add backward-compatible sheet layout settings to label profiles.';
    }

    private function addColumns(): void
    {
        $this->addSql("ALTER TABLE label_profiles ADD options_output_mode VARCHAR(255) DEFAULT 'single_label' NOT NULL");
        $this->addSql('ALTER TABLE label_profiles ADD options_sheet_width DOUBLE PRECISION DEFAULT 210 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD options_sheet_height DOUBLE PRECISION DEFAULT 297 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD options_sheet_margin_left DOUBLE PRECISION DEFAULT 7 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD options_sheet_margin_top DOUBLE PRECISION DEFAULT 13 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD options_sheet_horizontal_pitch DOUBLE PRECISION DEFAULT 66 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD options_sheet_vertical_pitch DOUBLE PRECISION DEFAULT 34 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD options_sheet_columns INTEGER DEFAULT 3 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD options_sheet_rows INTEGER DEFAULT 8 NOT NULL');
    }

    private function removeColumns(): void
    {
        foreach (['options_output_mode', 'options_sheet_width', 'options_sheet_height', 'options_sheet_margin_left',
            'options_sheet_margin_top', 'options_sheet_horizontal_pitch', 'options_sheet_vertical_pitch',
            'options_sheet_columns', 'options_sheet_rows'] as $column) {
            $this->addSql('ALTER TABLE label_profiles DROP COLUMN '.$column);
        }
    }

    public function mySQLUp(Schema $schema): void { $this->addColumns(); }
    public function mySQLDown(Schema $schema): void { $this->removeColumns(); }
    public function sqLiteUp(Schema $schema): void { $this->addColumns(); }
    public function sqLiteDown(Schema $schema): void { $this->removeColumns(); }
    public function postgreSQLUp(Schema $schema): void { $this->addColumns(); }
    public function postgreSQLDown(Schema $schema): void { $this->removeColumns(); }
}
