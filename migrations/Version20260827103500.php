<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260827103500 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add the preferred label output file type to label profiles';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql("ALTER TABLE label_profiles ADD output_format VARCHAR(255) DEFAULT 'pdf' NOT NULL");
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN output_format');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql("ALTER TABLE label_profiles ADD COLUMN output_format VARCHAR(255) DEFAULT 'pdf' NOT NULL");
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN output_format');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql("ALTER TABLE label_profiles ADD output_format VARCHAR(255) DEFAULT 'pdf' NOT NULL");
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN output_format');
    }
}
