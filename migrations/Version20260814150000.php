<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260814150000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Rename automatically migrated label sheets to Custom - <profile name>.';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql("UPDATE label_sheets s INNER JOIN label_profiles p ON p.label_sheet_id = s.id SET s.name = CONCAT('Custom - ', p.name) WHERE s.name = CONCAT(p.name, ' Sheet')");
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql("UPDATE label_sheets s INNER JOIN label_profiles p ON p.label_sheet_id = s.id SET s.name = CONCAT(p.name, ' Sheet') WHERE s.name = CONCAT('Custom - ', p.name)");
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->renameUp();
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->renameDown();
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->renameUp();
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->renameDown();
    }

    private function renameUp(): void
    {
        $this->addSql("UPDATE label_sheets SET name = 'Custom - ' || (SELECT name FROM label_profiles WHERE label_sheet_id = label_sheets.id LIMIT 1) WHERE EXISTS (SELECT 1 FROM label_profiles WHERE label_sheet_id = label_sheets.id AND label_sheets.name = label_profiles.name || ' Sheet')");
    }

    private function renameDown(): void
    {
        $this->addSql("UPDATE label_sheets SET name = (SELECT name FROM label_profiles WHERE label_sheet_id = label_sheets.id LIMIT 1) || ' Sheet' WHERE EXISTS (SELECT 1 FROM label_profiles WHERE label_sheet_id = label_sheets.id AND label_sheets.name = 'Custom - ' || label_profiles.name)");
    }
}
