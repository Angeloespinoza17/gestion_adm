<?php

namespace App\Http\Controllers;

use App\Services\PublicEducationalProjectDocumentResolver;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicEducationalProjectController extends Controller
{
    public function show(PublicEducationalProjectDocumentResolver $documentResolver): View
    {
        return view('public.pages.educational-project', [
            'projectDocument' => $documentResolver->metadata(),
            'summaryYear' => 2023,
            'projectLead' => 'El Proyecto Educativo Institucional reúne el horizonte formativo, los valores y las orientaciones que dan sentido al trabajo de toda la comunidad educativa.',
            'projectSections' => $this->projectSections(),
        ]);
    }

    public function download(PublicEducationalProjectDocumentResolver $documentResolver): BinaryFileResponse|StreamedResponse
    {
        return $documentResolver->download();
    }

    /**
     * Síntesis editorial del PEI 2023. El PDF descargable conserva el documento íntegro.
     *
     * @return array<int, array{id:string,short_title:string,eyebrow:string,title:string,summary:string,items:array<int,string>,quote?:string,icon:string}>
     */
    private function projectSections(): array
    {
        return [
            [
                'id' => 'identidad-institucional',
                'short_title' => 'Identidad',
                'eyebrow' => '01 · Horizonte institucional',
                'title' => 'Visión, misión y carisma',
                'summary' => 'La identidad del colegio vincula fe, cultura, formación académica y desarrollo valórico, inspirada en el legado de Madre Paulina.',
                'items' => [
                    'La visión convoca a ser una comunidad de Fe y Cultura comprometida con la civilización del amor.',
                    'La misión orienta una sólida preparación cristiano-católica, académica y valórica.',
                    'El carisma congregacional inspira una vida de servicio, alegría y compromiso con los demás.',
                ],
                'quote' => '«El amor sea el móvil de tu actuar»',
                'icon' => 'bi-stars',
            ],
            [
                'id' => 'sellos-formativos',
                'short_title' => 'Sellos',
                'eyebrow' => '02 · Rasgos distintivos',
                'title' => 'Tres sellos que orientan la formación',
                'summary' => 'Los sellos expresan las convicciones que el proyecto busca hacer visibles en la experiencia educativa cotidiana.',
                'items' => [
                    'Carisma y espiritualidad congregacional.',
                    'Educación humanista cristiana católica.',
                    'Formación integral.',
                ],
                'icon' => 'bi-bookmark-star',
            ],
            [
                'id' => 'valores-institucionales',
                'short_title' => 'Valores',
                'eyebrow' => '03 · Cultura compartida',
                'title' => 'Valores que se aprenden y se viven',
                'summary' => 'Nueve valores sostienen la convivencia y el desarrollo personal dentro de la comunidad educativa.',
                'items' => [
                    'Caridad, Fe y Verdad.',
                    'Alegría, Servicio y Libertad.',
                    'Humildad, Responsabilidad y Respeto.',
                ],
                'icon' => 'bi-heart',
            ],
            [
                'id' => 'perfil-estudiante',
                'short_title' => 'Las estudiantes',
                'eyebrow' => '04 · Perfil formativo',
                'title' => 'La estudiante que buscamos formar',
                'summary' => 'El perfil de egreso integra el crecimiento espiritual, personal, social y académico de cada una de nuestras estudiantes.',
                'items' => [
                    'Vive el carisma y la espiritualidad congregacional.',
                    'Construye su proyecto de vida con libertad y responsabilidad.',
                    'Se reconoce como un ser social, abierto al diálogo, el servicio y la participación.',
                    'Aprende a aprender y asume el cuidado de la creación.',
                ],
                'icon' => 'bi-person-hearts',
            ],
            [
                'id' => 'propuesta-educativa',
                'short_title' => 'Aprendizaje',
                'eyebrow' => '05 · Propuesta educativa',
                'title' => 'Una educación centrada en la persona',
                'summary' => 'La propuesta educativa comprende el aprendizaje como un proceso integral que acompaña las distintas dimensiones de la persona.',
                'items' => [
                    'La persona ocupa el centro del proceso formativo.',
                    'La ciudadanía y la inclusión orientan la vida en comunidad.',
                    'El aprendizaje promueve autonomía, participación y desarrollo integral.',
                ],
                'icon' => 'bi-lightbulb',
            ],
            [
                'id' => 'comunidad-gestion',
                'short_title' => 'Comunidad',
                'eyebrow' => '06 · Compromiso compartido',
                'title' => 'Agentes y áreas que hacen vida el proyecto',
                'summary' => 'La realización del PEI compromete a todos los integrantes de la comunidad y se organiza mediante áreas de gestión articuladas.',
                'items' => [
                    'Participan el sostenedor, directivos, docentes y asistentes de la educación.',
                    'Las familias forman parte activa del proceso educativo.',
                    'La gestión se articula en las áreas institucional, curricular, formación y convivencia, pastoral y recursos.',
                ],
                'icon' => 'bi-people',
            ],
        ];
    }
}
