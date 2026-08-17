<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260814153000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Replace automatically generated label sheets with the virtual Default legacy layout.';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE automatic_label_sheets (id INT NOT NULL PRIMARY KEY)');
        $this->removeAutomaticSheets("CONCAT('Custom - ', p.name)", "CONCAT(p.name, ' Sheet')");
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE automatic_label_sheets (id INTEGER NOT NULL PRIMARY KEY)');
        $this->removeAutomaticSheets("'Custom - ' || p.name", "p.name || ' Sheet'");
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE automatic_label_sheets (id INT NOT NULL PRIMARY KEY)');
        $this->removeAutomaticSheets("'Custom - ' || p.name", "p.name || ' Sheet'");
    }

    private function removeAutomaticSheets(string $customName, string $oldName): void
    {
        $this->addSql("INSERT INTO automatic_label_sheets (id) SELECT DISTINCT s.id FROM label_sheets s INNER JOIN label_profiles p ON p.label_sheet_id = s.id WHERE (s.name = $customName OR s.name = $oldName) AND s.page_size = 'CUSTOM' AND s.unit = 'mm' AND s.width = p.options_width AND s.height = p.options_height AND s.margin_left = 0 AND s.margin_top = 0 AND s.gutter_width = 0 AND s.gutter_height = 0 AND s.columns = 1 AND s.rows = 1 AND s.corner_radius = 0");
        $this->addSql('UPDATE label_profiles SET label_sheet_id = NULL WHERE label_sheet_id IN (SELECT id FROM automatic_label_sheets)');
        $this->addSql('DELETE FROM label_sheets WHERE id IN (SELECT id FROM automatic_label_sheets)');
        $this->addSql('DROP TABLE automatic_label_sheets');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->restoreAutomaticSheets("CONCAT('Custom - ', name)", "CONCAT('Custom - ', label_profiles.name)");
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->restoreAutomaticSheets("'Custom - ' || name", "'Custom - ' || label_profiles.name");
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->restoreAutomaticSheets("'Custom - ' || name", "'Custom - ' || label_profiles.name");
    }

    private function restoreAutomaticSheets(string $nameExpression, string $lookupExpression): void
    {
        $this->addSql("INSERT INTO label_sheets (name, unit, page_size, width, height, margin_left, margin_top, gutter_width, gutter_height, columns, rows, corner_radius, last_modified, datetime_added) SELECT $nameExpression, 'mm', 'CUSTOM', options_width, options_height, 0, 0, 0, 0, 1, 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM label_profiles WHERE label_sheet_id IS NULL");
        $this->addSql("UPDATE label_profiles SET label_sheet_id = (SELECT id FROM label_sheets WHERE label_sheets.name = $lookupExpression ORDER BY id DESC LIMIT 1) WHERE label_sheet_id IS NULL");
    }
}
