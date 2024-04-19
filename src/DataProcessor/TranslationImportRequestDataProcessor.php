<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\DataProcessor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use WhiteDigital\Translation\Api\Resource\TranslationImportRequestResource;
use WhiteDigital\Translation\Entity\Translation;
use WhiteDigital\Translation\Repository\TranslationRepository;

readonly class TranslationImportRequestDataProcessor implements ProcessorInterface
{
    private TranslationRepository $translationRepository;
    private PropertyAccessor $propertyAccessor;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->translationRepository = $entityManager->getRepository(Translation::class);
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    private function getExportAttributes(): array
    {
        return [
            'domain',
            'locale',
            'key',
            'translation'
        ];
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (!$operation instanceof Post) {
            throw new MethodNotAllowedHttpException(['POST'], 'Method not allowed');
        }

        if (!$data instanceof TranslationImportRequestResource) {
            throw new BadRequestException();
        }

        //Configure spreadsheet reader
        $spreadSheetReader = IOFactory::createReader(IOFactory::READER_XLSX)
            ->setLoadSheetsOnly('Translations')
            ->setReadDataOnly(true);

        //Load spreadsheet into memory?
        $spreadsheet = $spreadSheetReader->load($data->file?->getPathname());
        //create header row
        $worksheet = $spreadsheet->getActiveSheet();
        foreach ($worksheet->getRowIterator() as $row) {
            if ($this->isHeaderRow($row)) {
                continue;
            }
            foreach ($row->getCellIterator() as $cell) {

            }
        }
    }

    private function isHeaderRow(Row $row): bool
    {
        return $row->getRowIndex() === 1;
    }

    private function mapHeaderCells(Row $row)
    {
        foreach ($row->getCellIterator() as $headerCell) {

        }
    }
}
