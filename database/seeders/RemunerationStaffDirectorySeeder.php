<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Department;
use App\Models\Remuneration\RemunerationBookImport;
use App\Models\Remuneration\RemunerationBookImportRow;
use App\Models\Remuneration\RemunerationPayroll;
use App\Models\Staff;
use App\Models\User;
use App\Support\Rut;
use Database\Seeders\Support\PreventsProductionSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RemunerationStaffDirectorySeeder extends Seeder
{
    use PreventsProductionSeeding;

    private const RAW_STAFF = <<<'DATA'
RUT|Empleado|correo|FUNCION|DEPTO
7.530.527-3|SEPÚLVEDA AGUAYO ELIANIRA DEL CARMEN|directora@cnscvaldivia.cl|DIRECTORA|EQUIPO DIRECTIVO
13.521.283-0|TREUQUEMIL PEREZ PAMELA WALESKA|pamela.treuquemil@cnscvaldivia.cl|DOCENTE|APOYO UTP
11.705.187-0|AGUILEF OVALLE LILIAN|lilian.aguilef@cnscvaldivia.cl|DOCENTE|ARTES
17.925.118-3|AGUILERA TOLOSA CATALINA ELIZABETH|catalina.aguilera@cnscvaldivia.cl|ASISTENTE DE AULA|ASISTENTES DE AULA
14.592.311-5|AGUIRRE NEIRA ROSA|rosa.aguirre@cnscvaldivia.cl|INSPECTORA|CONVIVENCIA ESCOLAR
18.893.321-1|ALCAINO CASANOVA NICOL|nicol.alcaino@cnscvaldivia.cl|TALLERISTA|ACLE
12.344.112-5|ALVAREZ SAEZ KARLA|karla.alvarez@cnscvaldivia.cl|DOCENTE|ED. FISICA
7.883.662-8|ARANEDA FLAIG SERGIO|sergio.araneda@cnscvaldivia.cl|DOCENTE|CIENCIAS
19.248.917-2|ARAVENA AZOCAR VALENTINA|valentina.aravena@cnscvaldivia.cl|DOCENTE|HISTORIA
16.464.017-5|ARCOS VILLA LORETO|loreto.arcos@cnscvaldivia.cl|DOCENTE|INGLÉS
10.489.818-1|ARNES AVILA LORENA|lorena.arnes@cnscvaldivia.cl|DOCENTE|PRE ESCOLAR
17.360.118-2|ASENJO VILLAGRA ISABEL DEL CA|isabel.asenjo@cnscvaldivia.cl|DOCENTE|PIE
20.337.335-K|BARRÍA VIVAR SANDRO|sandro.barria@cnscvaldivia.cl|DOCENTE|RELIGIÓN
15.883.259-3|BARRIGA BARRIGA FABIOLA|fabiola.barriga@cnscvaldivia.cl|DOCENTE|LENGUAJE
18.733.344-K|BARRIGA SOTO JORGE|jorge.barriga@cnscvaldivia.cl|DOCENTE|ED. FISICA
26.661.077-7|BELIZAIRE LUCKSERNE|luckserne.belizaire@cnscvaldivia.cl|NOCHERO|AUXILIARES
15.375.920-0|CACERES RETAMAL DANIELA|daniela.caceres@cnscvaldivia.cl|COORDINADORA CICLO|UTP
13.662.963-8|CAMPOS CAMPOS CAROLINA|carolina.campos@cnscvaldivia.cl|DOCENTE|RELIGIÓN
18.589.813-k|CARCAMO CARTES LLINETH IVONE|llineth.carcamo@cnscvaldivia.cl|AUXILIAR DE ASEO|AUXILIARES
12.432.035-6|CARCAMO DURAN MARISOL|marisol.carcamo@cnscvaldivia.cl|DOCENTE|PIE
16.871.474-2|CARDENAS ZEPEDA CAMILA|camila.cardenas@cnscvaldivia.cl|COORDINADORA PIE|UTP
19.306.401-9|CASAS ANTEPICHUN JAVIER|javier.casas@cnscvaldivia.cl|CALDERERO|AUXILIARES
15.639.829-2|CASTRO NARANJO MARIA|maria.castro@cnscvaldivia.cl|ORIENTADORA|CONVIVENCIA ESCOLAR
7.797.088-6|CATALAN CONCHA ELIANA MARGARITA|eliana.catalan@cnscvaldivia.cl|COORDINADOR PASTORAL|PASTORAL
10.135.082-7|CAYUL CAYUL CARLOS|carlos.cayul@cnscvaldivia.cl|AUXILIAR DE MANTENCIÓN|AUXILIARES
9.316.594-2|COCIO ARTEAGA NORA|nora.cocio@cnscvaldivia.cl|AUXILIAR DE ASEO|AUXILIARES
18.590.868-2|CONTRERAS GARRIDO ALEJANDRA|alejandra.contreras@cnscvaldivia.cl|EDUCADOR/A DIFERENCIAL|PIE
10.546.155-0|CORTEZ LEON MARITZA|maritza.cortez@cnscvaldivia.cl|DOCENTE DIFERENCIAL|PIE
16.016.626-6|CRISTI CORDERO LIZET|lizet.cristi@cnscvaldivia.cl|COORDINADORA CICLO|UTP
10.365.594-3|DAVINSON SANTANA LAURA|laura.davinson@cnscvaldivia.cl|AUXILIAR DE ASEO|AUXILIARES
14.501.832-3|DELGADO NAVARRO BERNARDA|bernarda.delgado@cnscvaldivia.cl|DOCENTE|BÁSICA
8.480.224-7|DIAZ MOYA EDISON|edison.diaz@cnscvaldivia.cl|CAPELLÁN|PASTORAL
16.465.655-1|DURAN ALARCON LIZBETH|lizbeth.duran@cnscvaldivia.cl|TENS|ADMINISTRATIVO
17.197.770-3|DURAN REYES DANIELA|daniela.duran@cnscvaldivia.cl|TRABAJADORA SOCIAL|CONVIVENCIA ESCOLAR
17.863.807-6|ECHEVERRIA HEREDIA MARIA|maria.echeverria.heredia@cnscvaldivia.cl|INSPECTORA|CONVIVENCIA ESCOLAR
20.134.436-0|ELLES MATUS PAULINA|paulina.elles@cnscvaldivia.cl|DOCENTE|BÁSICA
18.367.029-8|ESPINOZA RODRIGUEZ ANGELO|angelo.espinoza@cnscvaldivia.cl|ADMINISTRADOR|EQUIPO DIRECTIVO
15.661.237-5|FISCHER GUARDA BARBARA|barbara.fischer@cnscvaldivia.cl|DOCENTE|BÁSICA
18.886.573-9|FLANDEZ SOLIS VANIA ESTHER|vania.flandez@cnscvaldivia.cl|EDUCADOR/A DIFERENCIAL|PIE
17.201.478-K|FREZ GUARDA PAMELA|pamela.frez@cnscvaldivia.cl|DOCENTE|ED. FISICA
19.556.316-0|FUENTES MOREIRA TAMARA|tamara.fuentes@cnscvaldivia.cl|ASISTENTE DE PARVULO|PRE ESCOLAR
20.537.817-0|FUENTES SOTO JAVIERA|javiera.fuentes@cnscvaldivia.cl|TALLERISTA|ACLE
16.068.182-9|GARRIDO TAIBA SEBASTIAN EMILIO|sebastian.garrido@cnscvaldivia.cl|SUB DIRECTOR CURRICULAR| EQUIPO DIRECTIVO
20.540.748-0|GATICA LEON ANAIS|anais.gatica@cnscvaldivia.cl|DOCENTE DIFERENCIAL|PIE
16.262.875-5|GODOY ORTEGA STEFANY|stefany.godoy@cnscvaldivia.cl|COORDINADORA CICLO|UTP
9.167.469-6|GOMEZ CARRILLO SIGISFREDO|sigisfredo.gomez@cnscvaldivia.cl|DOCENTE|ARTES
13.499.523-8|GOMEZ MORENO CLAUDIA|claudia.gomez@cnscvaldivia.cl|DOCENTE|BÁSICA
18.590.205-6|GONZALEZ RISCO GRACIELA|graciela.gonzalez@cnscvaldivia.cl|ASISTENTE DE PARVULO|PRE ESCOLAR
10.752.672-2|HERNANDEZ MANCILLA RAQUEL ALBINA|raquel.hernandez@cnscvaldivia.cl|DOCENTE|BÁSICA
20.601.761-9|HUILIPAN ALMENDRA ALEJANDRA|almendra.huilipan@cnscvaldivia.cl|PSICOLOGO/A|CONVIVENCIA ESCOLAR
9.896.711-7|JELDES VIDELA NELSON|nelson.jeldes@cnscvaldivia.cl|DOCENTE|MATEMATICAS
16.340.180-0|JIMENEZ HERRERA PAOLA|paola.jimenez@cnscvaldivia.cl|DOCENTE|LENGUAJE
8.393.614-2|LABRANA BORQUEZ LUIS|luis.labrana@cnscvaldivia.cl|DOCENTE|ARTES
15.688.302-6|LARA LEAL MANUEL|manuel.lara@cnscvaldivia.cl|INFORMATICO|ADMINISTRATIVO
11.411.279-8|LEAL HENRIQUEZ MARIANA|mariana.leal@cnscvaldivia.cl|INSPECTORA|CONVIVENCIA ESCOLAR
11.705.349-0|LEAL HENRIQUEZ PATRICIA|patricia.leal@cnscvaldivia.cl|DOCENTE|CIENCIAS
15.261.616-3|LEIVA ALDERETE PAMELA|pamela.leiva@cnscvaldivia.cl|SUB DIRECTORA PASTORAL|EQUIPO DIRECTIVO
17.201.274-4|LIENLAF HUICHAMAN YASNA|yasna.lienlaf@cnscvaldivia.cl|DOCENTE|HISTORIA
12.504.058-6|LIZAMA DONOSO PATRICIO|patricio.lizama@cnscvaldivia.cl|DOCENTE|MATEMATICAS
13.696.834-3|LOPEZ CARVAJAL MACARENA|macarena.lopez@cnscvaldivia.cl|DOCENTE|LENGUAJE
15.225.586-1|LOPEZ SALAZAR OSCAR|oscar.lopez@cnscvaldivia.cl|AUXILIAR DE ASEO|AUXILIARES
18.275.214-2|MANCILLA ARRIAGADA KARLA|karla.mancilla@cnscvaldivia.cl|DOCENTE|MATEMATICAS
18.317.234-4|MANRIQUEZ HERNANDEZ NATALIA|natalia.manriquez@cnscvaldivia.cl|DOCENTE|ARTES
17.200.315-K|MARTINEZ CORTEZ NELIDA|nelida.martinez@cnscvaldivia.cl|ASISTENTE DE AULA|APOYO UTP
16.805.981-7|MARTINEZ DAGUERRE ALEJANDRO|alejandro.martinez@cnscvaldivia.cl|DOCENTE|CIENCIAS
19.250.336-1|MARTINEZ DELGADO CAMILA|camila.martinez@cnscvaldivia.cl|DOCENTE|LENGUAJE
16.806.413-6|MAYOR BAEZA SANDRA|sandra.mayor@cnscvaldivia.cl|ASISTENTE DE AULA|APOYO UTP
18.324.156-7|MIRANDA NAHUELQUIN MAURICIO|mauricio.miranda@cnscvaldivia.cl|DOCENTE|ED. FISICA
19.554.272-4|MONCADA KOPP CATALINA|catalina.moncada@cnscvaldivia.cl|DOCENTE|CIENCIAS
20.018.363-0|MONTECINOS LOPEZ MARIELA ALEJANDRA|mariela.montecinos@cnscvaldivia.cl|DOCENTE|MATEMATICAS
12.201.462-2|MUNOZ CARCAMO MARISA|marisa.munoz@cnscvaldivia.cl|DOCENTE|BÁSICA
18.491.306-2|NAHUELPAN DIAZ PEDRO|pedro.nahuelpan@cnscvaldivia.cl|ENCARGADO RRHH|CONTABILIDAD
16.320.387-1|NAVARRO AGUILAR SOFIA|sofia.navarro@cnscvaldivia.cl|AUXILIAR DE ASEO|AUXILIARES
14.281.906-6|NUNEZ NUNEZ KARINA|karina.nunez@cnscvaldivia.cl|DOCENTE|INGLÉS
21.073.161-k|OJEDA BARRIA CYNTHIA MACARENA|cynthia.ojeda@cnscvaldivia.cl|TALLERISTA|ACLE
11.425.809-1|OJEDA VELASQUEZ ERNESTO|ernesto.ojeda@cnscvaldivia.cl|NOCHERO|AUXILIARES
18.359.195-9|ORELLANA AVENDAÑO FELIPE ANDRES|felipe.orellana@cnscvaldivia.cl|DOCENTE|INGLÉS
7.321.237-5|ORMENO CLERICUS CECILIA|cecilia.ormeno@cnscvaldivia.cl|DOCENTE|RELIGIÓN
20.346.491-6|ORTIZ MANCILLA KATHERIN YULISSA|katherin.ortiz@cnscvaldivia.cl|INSPECTORA|CONVIVENCIA ESCOLAR
17.864.979-5|OYARZUN CERON FELIPE|felipe.oyarzun@cnscvaldivia.cl|EDUCADOR/A DIFERENCIAL|PIE
11.086.516-3|PAILLA MARIPAN LUCIA|lucia.pailla@cnscvaldivia.cl|AUXILIAR DE ASEO|AUXILIARES
20.009.674-6|PAIRO CHEUQUEMAN MABEL IGNACIA|mabel.pairo@cnscvaldivia.cl|CONTADORA|CONTABILIDAD
17.067.594-0|PANTOJA PINILLA CAMILA|camila.pantoja@cnscvaldivia.cl|DOCENTE|INGLÉS
11.707.036-0|PAREDES SANCHEZ JOVITA|jovita.paredes@cnscvaldivia.cl|INSPECTORA|CONVIVENCIA ESCOLAR
16.145.238-6|PENAILILLO MATAMALA VIVIANA|viviana.penailillo@cnscvaldivia.cl|ASISTENTE DE CONVIVENCIA ESCOLAR|CONVIVENCIA ESCOLAR
16.894.729-1|PEREZ DELGADO PAULINA|paulina.perez@cnscvaldivia.cl|DOCENTE|LENGUAJE
16.651.348-0|PEREZ HERNANDEZ ELSA|elsa.perez@cnscvaldivia.cl|ENCARGADA CENTRO APUNTES|ADMINISTRATIVO
15.547.361-4|PEREZ REYES DAMARY|damary.perez@cnscvaldivia.cl|BIBLIOTECARIA|ADMINISTRATIVO
16.161.354-1|perez rivas BORIS|boris.perez@cnscvaldivia.cl|EDUCADOR/A DIFERENCIAL|APOYO UTP
19.624.244-9|PEREZ VEGAS PATRICIO|patricio.perez@cnscvaldivia.cl|DOCENTE|CIENCIAS
15.294.891-3|PINILLA SILVA CAROLINA|carolina.pinilla@cnscvaldivia.cl|DOCENTE|PRE ESCOLAR
16.464.590-8|PLASENCIO GONZALEZ MARIA|maria.plasencio@cnscvaldivia.cl|INSPECTORA|CONVIVENCIA ESCOLAR
10.777.777-6|PROBOSTE FUENTES AIDA|aida.proboste@cnscvaldivia.cl|SECRETARIA|ADMINISTRATIVO
17.631.373-0|PROSCHLE ELVIS|elvis.proschle@cnscvaldivia.cl|COORDINADOR PASTORAL|PASTORAL
17.247.866-2|QUINTUPURRAI SILVA PRISCILA|priscila.quintupurrai@cnscvaldivia.cl|ASISTENTE DE AULA|APOYO UTP
20.527.976-8|ramos henriquez heber|heber.ramos@cnscvaldivia.cl|TALLERISTA|ACLE
20.021.761-6|RAMOS MORA CRISTIAN|cristian.ramos@cnscvaldivia.cl|DOCENTE|ARTES
9.739.354-0|REYES KRAUSE CLAUDIA|claudia.reyes@cnscvaldivia.cl|DOCENTE|MATEMATICAS
20.134.838-2|RIVAS PEREZ CAMILA FRANCISCA|camila.rivas@cnscvaldivia.cl|EDUCADOR/A DIFERENCIAL|APOYO UTP
8.159.598-4|RIVERA GARRIDO IVAN|ivan.rivera@cnscvaldivia.cl|AUXILIAR DE MANTENCIÓN|MANTENCIÓN
17.962.928-3|ROJAS HUICHAQUEO CARLA|carla.rojas@cnscvaldivia.cl|PSICOLOGO/A|CONVIVENCIA ESCOLAR
19.250.626-3|RUIZ FLORES GABRIELA|gabriela.ruiz@cnscvaldivia.cl|DOCENTE|HISTORIA
18.776.541-2|SALDIAS SANDOVAL CAMILA|camila.saldias@cnscvaldivia.cl|ASISTENTE PIE|PIE
26.100.395-3|SANCHEZ MORA LINDA ROSANA|linda.sanchez@cnscvaldivia.cl|DOCENTE|RELIGIÓN
15.269.295-1|SANDOVAL VILLARROEL JEAQUELINE|jeaqueline.sandoval@cnscvaldivia.cl|PREVENCIONISTA DE RIESGOS|ADMINISTRATIVO
17.327.530-7|SANHUEZA HENRIQUEZ PAULA|paula.sanhueza@cnscvaldivia.cl|DOCENTE|LENGUAJE
16.805.291-K|SCHEEL RUIZ NICOL|nicol.scheel@cnscvaldivia.cl|DOCENTE|ED. FISICA
17.115.881-8|SEGUEL ARAVENA YESENIA|yesenia.seguel@cnscvaldivia.cl|FONOAUDIOLOGO/A|PIE
7.857.958-7|SIEGLE HERMOSILLA BRUNO|bruno.siegle@cnscvaldivia.cl|PORTERO/A|ADMINISTRATIVO
11.956.246-5|SOTO GUTIERREZ MANUEL|manuel.soto@cnscvaldivia.cl|SUB DIRECTOR DE FORMACIÓN Y CONVIVENCIA ESCOLAR|EQUIPO DIRECTIVO
21.764.439-9|TOPP PALMA CAROLINA|carolina.topp@cnscvaldivia.cl|DOCENTE|CIENCIAS
10.619.216-2|TROPA ORTEGA EMERSON|emerson.tropa@cnscvaldivia.cl|DOCENTE|LENGUAJE
9.308.098-K|ULLOA ROSAS ROXY|roxy.ulloa@cnscvaldivia.cl|DOCENTE|ARTES
15.758.697-1|VALDEBENITO NOVA SOLANGE|solange.valdebenito@cnscvaldivia.cl|DOCENTE|BÁSICA
16.828.075-0|VALDIVIA POBLETE DANIELA PATRICIA|daniela.valdivia@cnscvaldivia.cl|INSPECTORA|CONVIVENCIA ESCOLAR
17.549.797-8|VALENZUELA QUIJON FELIPE|felipe.valenzuela@cnscvaldivia.cl|DOCENTE|MATEMATICAS
10.016.786-7|VALLADARES ROJAS LUCILA|lucila.valladares@cnscvaldivia.cl|AUXILIAR DE ASEO|AUXILIARES
11.324.979-K|VALLADARES ROJAS SARA|sara.valladares@cnscvaldivia.cl|PORTERO/A|ADMINISTRATIVO
11.424.333-7|VASQUEZ FERNANDEZ MARISOL|marisol.vasquez@cnscvaldivia.cl|DOCENTE|BÁSICA
18.289.606-3|VASQUEZ FUENTEALBA SAMUEL|samuel.vasquez@cnscvaldivia.cl|DOCENTE|MATEMATICAS
20.017.070-9|VASQUEZ HUENULEF MARIA PAZ|maria.vasquez@cnscvaldivia.cl|AUXILIAR DE ASEO|AUXILIARES
15.294.537-K|VELASQUEZ VALLADARES ALEJANDRO|alejandro.velasquez@cnscvaldivia.cl|PSICOLOGO/A|PIE
15.883.065-5|VERA REYES YESSICA|yessica.vera@cnscvaldivia.cl|ASISTENTE DE AULA|APOYO UTP
15.294.634-1|VERGARA VERGARA YENYFER|yenyfer.vergara@cnscvaldivia.cl|INSPECTORA|CONVIVENCIA ESCOLAR
DATA;

    private int $createdStaff = 0;

    private int $updatedStaff = 0;

    private int $createdUsers = 0;

    private int $updatedUsers = 0;

    private int $linkedBookRows = 0;

    private int $linkedPayrolls = 0;

    public function run(): void
    {
        $this->preventProductionSeeding();
        $this->call(RemunerationDepartmentsAndFunctionsSeeder::class);

        DB::transaction(function (): void {
            $staffByBookRut = [];

            foreach ($this->rows() as $row) {
                $staff = $this->upsertStaff($row);
                $this->upsertUser($staff, $row['email']);

                $staffByBookRut[$this->bookRutKey($staff->rut)] = $staff->id;
            }

            $touchedImportIds = $this->linkBookRows($staffByBookRut);
            $this->linkImportedPayrolls();
            $this->refreshImportCounters($touchedImportIds);
        });

        $this->command?->info(sprintf(
            'Dotación remuneraciones: %d funcionarios creados, %d actualizados, %d usuarios creados, %d usuarios actualizados, %d filas de libro vinculadas y %d liquidaciones importadas ajustadas.',
            $this->createdStaff,
            $this->updatedStaff,
            $this->createdUsers,
            $this->updatedUsers,
            $this->linkedBookRows,
            $this->linkedPayrolls,
        ));
    }

    /**
     * @param  array{rut:string,name:string,email:string,function:string,department:string}  $row
     */
    private function upsertStaff(array $row): Staff
    {
        $rut = Rut::normalize($row['rut']);
        if (!$rut) {
            throw new \RuntimeException("RUT inválido en dotación: {$row['rut']}");
        }

        $staff = $this->staffByRut($rut) ?: new Staff();
        $cargo = $this->cargoFor($row['function']);
        $department = $this->departmentFor($row['department']);
        $email = mb_strtolower($row['email']);
        $emailOwner = Staff::query()
            ->where('institutional_email', $email)
            ->when($staff->exists, fn ($query) => $query->where('id', '!=', $staff->id))
            ->first();

        $payload = [
            'full_name' => $this->normalizeName($row['name']),
            'rut' => $rut,
            'cargo_id' => $cargo->id,
            'status' => 'activo',
            'active' => true,
            'updated_by' => $this->actorId(),
        ];

        if (!$emailOwner) {
            $payload['institutional_email'] = $email;
        } else {
            $this->command?->warn("Correo institucional omitido para {$row['name']}: {$email} ya está usado por otro funcionario.");
        }

        if (!$staff->exists) {
            $payload['created_by'] = $this->actorId();
            $payload['internal_notes'] = 'Dotación base importada desde nómina de remuneraciones.';
            $this->createdStaff++;
        } else {
            $this->updatedStaff++;
        }

        $staff->fill($payload);
        $staff->save();
        $staff->departments()->syncWithoutDetaching([$department->id]);

        return $staff->fresh(['departments', 'cargo']);
    }

    private function upsertUser(Staff $staff, string $email): void
    {
        $email = mb_strtolower(trim($email));
        $user = User::query()->where('staff_id', $staff->id)->first()
            ?: User::query()->where('email', $email)->first()
            ?: new User();

        if ($user->exists && $user->staff_id && (int) $user->staff_id !== (int) $staff->id) {
            $this->command?->warn("Usuario omitido para {$staff->full_name}: {$email} ya está vinculado a otro funcionario.");
            return;
        }

        $emailTakenByOther = User::query()
            ->where('email', $email)
            ->when($user->exists, fn ($query) => $query->where('id', '!=', $user->id))
            ->exists();

        if (!$user->exists && $emailTakenByOther) {
            $this->command?->warn("Usuario omitido para {$staff->full_name}: {$email} ya existe.");
            return;
        }

        $wasNew = !$user->exists;
        $user->fill([
            'name' => $staff->full_name,
            'email' => $emailTakenByOther ? $user->email : $email,
            'user_type' => 'staff',
            'active' => true,
            'staff_id' => $staff->id,
            'cargo_id' => $staff->cargo_id,
        ]);

        if ($wasNew || !$user->password) {
            $user->password = Hash::make((string) Rut::normalize($staff->rut));
            $user->email_verified_at = $user->email_verified_at ?: now();
        }

        $user->save();

        if ($wasNew) {
            $this->createdUsers++;
        } else {
            $this->updatedUsers++;
        }
    }

    /**
     * @param  array<string, int>  $staffByBookRut
     * @return array<int, int>
     */
    private function linkBookRows(array $staffByBookRut): array
    {
        $touchedImportIds = [];

        RemunerationBookImportRow::query()
            ->get(['id', 'book_import_id', 'staff_id', 'rut'])
            ->each(function (RemunerationBookImportRow $row) use ($staffByBookRut, &$touchedImportIds): void {
                $staffId = $staffByBookRut[$this->bookRutKey($row->rut)] ?? null;

                if (!$staffId || (int) ($row->staff_id ?? 0) === (int) $staffId) {
                    return;
                }

                $row->forceFill(['staff_id' => $staffId])->save();
                $touchedImportIds[$row->book_import_id] = $row->book_import_id;
                $this->linkedBookRows++;
            });

        return array_values($touchedImportIds);
    }

    private function linkImportedPayrolls(): void
    {
        RemunerationPayroll::query()
            ->where('source', 'imported')
            ->whereNotNull('book_import_id')
            ->whereNotNull('source_row_number')
            ->get()
            ->each(function (RemunerationPayroll $payroll): void {
                $row = RemunerationBookImportRow::query()
                    ->where('book_import_id', $payroll->book_import_id)
                    ->where('row_number', $payroll->source_row_number)
                    ->first();

                if (!$row?->staff_id || (int) $payroll->staff_id === (int) $row->staff_id) {
                    return;
                }

                $staff = Staff::query()->find($row->staff_id);
                if (!$staff) {
                    return;
                }

                $snapshot = $payroll->snapshot ?? [];
                $snapshot['staff'] = $staff->only(['id', 'full_name', 'rut', 'birth_date', 'start_date', 'contract_type', 'contract_hours', 'cargo_id']);

                $payroll->forceFill([
                    'staff_id' => $staff->id,
                    'snapshot' => $snapshot,
                    'updated_by' => $this->actorId(),
                ])->save();

                $this->linkedPayrolls++;
            });
    }

    /**
     * @param  array<int, int>  $importIds
     */
    private function refreshImportCounters(array $importIds): void
    {
        if ($importIds === []) {
            return;
        }

        RemunerationBookImport::query()
            ->whereIn('id', $importIds)
            ->with('rows:id,book_import_id,staff_id')
            ->get()
            ->each(function (RemunerationBookImport $import): void {
                $rowCount = $import->rows->count();
                $matchedCount = $import->rows->whereNotNull('staff_id')->count();
                $summary = $import->summary ?? [];
                $summary['row_count'] = $rowCount;
                $summary['matched_count'] = $matchedCount;
                $summary['unmatched_count'] = max(0, $rowCount - $matchedCount);

                $import->forceFill([
                    'row_count' => $rowCount,
                    'matched_count' => $matchedCount,
                    'unmatched_count' => max(0, $rowCount - $matchedCount),
                    'summary' => $summary,
                    'updated_by' => $this->actorId(),
                ])->save();
            });
    }

    private function staffByRut(string $rut): ?Staff
    {
        $key = $this->bookRutKey($rut);

        return Staff::query()
            ->get()
            ->first(fn (Staff $staff) => $this->bookRutKey($staff->rut) === $key);
    }

    private function cargoFor(string $name): Cargo
    {
        $normalized = $this->comparisonKey($name);
        $cargo = Cargo::query()
            ->get()
            ->first(fn (Cargo $cargo) => $this->comparisonKey($cargo->name) === $normalized);

        if ($cargo) {
            return $cargo;
        }

        $name = $this->normalizeName($name);

        return Cargo::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => 'Función importada desde dotación de remuneraciones.',
            'active' => true,
        ]);
    }

    private function departmentFor(string $name): Department
    {
        $normalized = $this->comparisonKey($name);
        $department = Department::query()
            ->get()
            ->first(fn (Department $department) => $this->comparisonKey($department->name) === $normalized);

        if ($department) {
            return $department;
        }

        $name = $this->normalizeName($name);

        return Department::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => 'Departamento importado desde dotación de remuneraciones.',
            'active' => true,
            'sort_order' => ((int) Department::query()->max('sort_order')) + 1,
        ]);
    }

    /**
     * @return array<int, array{rut:string,name:string,email:string,function:string,department:string}>
     */
    private function rows(): array
    {
        return collect(preg_split('/\R/u', trim(self::RAW_STAFF)) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->reject(fn (string $line) => str_starts_with($line, 'RUT|'))
            ->map(function (string $line): array {
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) !== 5) {
                    throw new \RuntimeException("Fila inválida en dotación de remuneraciones: {$line}");
                }

                return [
                    'rut' => $parts[0],
                    'name' => $parts[1],
                    'email' => mb_strtolower($parts[2]),
                    'function' => $parts[3],
                    'department' => $parts[4],
                ];
            })
            ->unique(fn (array $row) => $this->bookRutKey($row['rut']))
            ->values()
            ->all();
    }

    private function normalizeName(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?: '';

        return mb_strtoupper($value, 'UTF-8');
    }

    private function comparisonKey(string $value): string
    {
        $value = Str::ascii($this->normalizeName($value));

        return preg_replace('/[^A-Z0-9]+/u', '', $value) ?: '';
    }

    private function bookRutKey(?string $rut): string
    {
        return strtoupper(preg_replace('/[^0-9K]/i', '', (string) $rut) ?? '');
    }

    private function actorId(): ?int
    {
        return User::query()->where('email', env('SUPER_ADMIN_EMAIL', 'superadmin@cnscgestion.cl'))->value('id')
            ?: User::query()->value('id');
    }
}
