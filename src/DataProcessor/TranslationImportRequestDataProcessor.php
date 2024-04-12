<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\DataProcessor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\HeaderUtils;
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

        //create writable spreadsheet
        $spreadsheet = new Spreadsheet();
        //create header row
        $worksheet = $spreadsheet->getActiveSheet();
        $this->createHeaderRow($worksheet);
        $this->styleHeaderRow($worksheet);
        //query data in batches
        /** @var Translation $translation */
        $y = 2;
        foreach ($this->translationRepository->findAllQuery()->toIterable() as $translation) {
            $x = 1;
            foreach ($this->getExportAttributes() as $exportAttribute) {
                $worksheet->setCellValue([$x, $y], $this->propertyAccessor->getValue($translation, $exportAttribute));
                $x++;
            }
            $y++;
        }
        //return written spreadsheet
        $spreadSheetWriter = IOFactory::createWriter($spreadsheet, IOFactory::WRITER_XLSX);
        $tempFile = tempnam(sys_get_temp_dir(), 'translations_export_');
        $spreadSheetWriter->save($tempFile);

        $response = new BinaryFileResponse($tempFile);
        $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'filename.xlsx');
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', $disposition);
        $response->deleteFileAfterSend();

        return $response;
    }

    private function createHeaderRow(Worksheet $worksheet): void
    {
        foreach ($this->getExportAttributes() as $idx => $exportAttribute) {
            $worksheet->setCellValue([1 + $idx, 1], ucfirst($exportAttribute));
        }
    }

    private function styleHeaderRow(Worksheet $worksheet): void
    {
        foreach ($this->getExportAttributes() as $idx => $exportAttribute) {
            $worksheet->getStyle([1 + $idx, 1])->getFont()->setBold(true);
        }
    }
}
