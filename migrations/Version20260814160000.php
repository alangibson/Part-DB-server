<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260814160000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Store label width and height as part of reusable label sheets.';
    }

    public function mySQLUp(Schema $schema): void { $this->addColumns(); }
    public function sqLiteUp(Schema $schema): void { $this->addColumns(); }
    public function postgreSQLUp(Schema $schema): void { $this->addColumns(); }
    public function mySQLDown(Schema $schema): void { $this->downColumns(); }
    public function sqLiteDown(Schema $schema): void { $this->downColumns(); }
    public function postgreSQLDown(Schema $schema): void { $this->downColumns(); }

    private function addColumns(): void
    {
        $this->addSql('ALTER TABLE label_sheets ADD label_width DOUBLE PRECISION DEFAULT 50 NOT NULL');
        $this->addSql('ALTER TABLE label_sheets ADD label_height DOUBLE PRECISION DEFAULT 30 NOT NULL');
        $this->addSql('UPDATE label_sheets SET label_width = COALESCE((SELECT options_width FROM label_profiles WHERE label_sheet_id = label_sheets.id ORDER BY id LIMIT 1), label_width), label_height = COALESCE((SELECT options_height FROM label_profiles WHERE label_sheet_id = label_sheets.id ORDER BY id LIMIT 1), label_height)');
    }

    private function downColumns(): void
    {
        $this->addSql('ALTER TABLE label_sheets DROP COLUMN label_width');
        $this->addSql('ALTER TABLE label_sheets DROP COLUMN label_height');
    }
}
