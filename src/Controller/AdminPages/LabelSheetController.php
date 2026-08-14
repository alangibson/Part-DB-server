<?php

declare(strict_types=1);

namespace App\Controller\AdminPages;

use App\Entity\LabelSystem\LabelSheet;
use App\Form\LabelSheetType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/label_sheet')]
class LabelSheetController extends AbstractController
{
    #[Route('/', name: 'label_sheet_list')]
    public function list(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('@labels.read_profiles');

        return $this->redirectToRoute('label_sheet_new');
    }

    #[Route('/new', name: 'label_sheet_new')]
    #[Route('/{id}/edit', name: 'label_sheet_edit', requirements: ['id' => '\\d+'])]
    public function edit(Request $request, EntityManagerInterface $em, ?LabelSheet $sheet = null): Response
    {
        $isNew = !$sheet instanceof LabelSheet;
        $this->denyAccessUnlessGranted($isNew ? '@labels.create_profiles' : '@labels.edit_profiles');
        $sheet ??= new LabelSheet();
        $form = $this->createForm(LabelSheetType::class, $sheet);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($sheet);
            $em->flush();
            $this->addFlash('success', 'entity.edit_flash');

            return $this->redirectToRoute('label_sheet_edit', ['id' => $sheet->getID()]);
        }

        return $this->render('admin/label_sheet_edit.html.twig', [
            'sheet' => $sheet,
            'form' => $form,
            'sheets' => $em->getRepository(LabelSheet::class)->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/{id}', name: 'label_sheet_delete', methods: ['POST'])]
    public function delete(LabelSheet $sheet, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('@labels.delete_profiles');
        if ($this->isCsrfTokenValid('delete-label-sheet-'.$sheet->getID(), (string) $request->request->get('_token'))) {
            $profilesUsingSheet = $em->getRepository(\App\Entity\LabelSystem\LabelProfile::class)->count(['label_sheet' => $sheet]);
            if ($profilesUsingSheet > 0) {
                $this->addFlash('error', 'label_sheet.delete.in_use');

                return $this->redirectToRoute('label_sheet_edit', ['id' => $sheet->getID()]);
            }
            $em->remove($sheet);
            $em->flush();
        }

        return $this->redirectToRoute('label_sheet_list');
    }
}
