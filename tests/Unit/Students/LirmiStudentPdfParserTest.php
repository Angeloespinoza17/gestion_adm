<?php

namespace Tests\Unit\Students;

use App\Services\Students\LirmiStudentPdfParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LirmiStudentPdfParserTest extends TestCase
{
    public function test_it_parses_student_guardian_health_enrollment_and_pie_fields(): void
    {
        $studentPage = <<<'TEXT'
FICHA DE MATRÍCULA 2026
ANTECEDENTES DEL ESTUDIANTE
Nombre Isidora Ignacia Aburto Espinoza Curso: Primer Nivel Transición A
Estado: Matriculado Número de matricula: 1
Fecha ingreso: 04/03/2026 Fecha matrícula: 04/03/2026
RUN: 275580171 Fecha de nacimiento 16/06/2021
Género FEMENINO Nacionalidad CHILENA
Dirección Clemente Escobar 1511 Comuna: Valdivia
Correo electrónico isidora.aburto@cnscvaldivia.cl Teléfono Celular 988961590
Colegio procedencia Nombre contacto emergencia Cristian Aburto
Teléfono emergencia 988961590 Vive con AMBOS PADRES
Religión Catolicismo ¿Acepta clases de religión en el colegio? Si
Etnia Mapuche
ANTECEDENTES DE SALUD
Estatura (cm) 105.00 Peso (kg) 15.00
Grupo Sanguíneo O+ Alergias a alimentos NO
Alergias a medicamentos NO Medicamentos contraindicados NO
Enfermedades crónicas NINGUNA
¿Apto para Educación Física? Si Sistema de Previsión Fonasa B
¿Posee seguro escolar privado? No Consultorio o clínica donde se atiende Hospital Regional
Observaciones Sin información © 2026
TEXT;

        $guardianPage = <<<'TEXT'
APODERADOS Y FAMILIARES
Apoderado titular
Nombres y Apellidos Cristian Marcelo Aburto Vaez Pasaporte
RUN 192481341 Parentesco Padre
Domicilio Clemente Escobar 1511 Comuna Valdivia
Teléfono Celular +56988961590 Correo electrónico cristian.aburto77@gmail.com
Autorización a que se fotografíe o se grabe a su estudiante Si Autorizado para retirar del establecimiento Si
Estado civil Soltero(a) Nivel educacional PROFESIONAL INCOMPLETA
Ultimo Nivel educacional Sin información Ocupación Trabajador Dependiente
Apoderado suplente
No existe un Apoderado secundario registrado
PROGRAMA DE INTEGRACIÓN
Permanencia PIE SI Tipo Permanencia Transitorio
Diagnóstico Trastorno del Lenguaje (TL)
El apoderado declara conocer el reglamento.
TEXT;

        $records = (new LirmiStudentPdfParser)->parsePages([$studentPage, $guardianPage]);

        $this->assertCount(1, $records);
        $this->assertSame('Isidora Ignacia', $records[0]['profile']['first_name']);
        $this->assertSame('Aburto Espinoza', $records[0]['profile']['last_name']);
        $this->assertSame('27558017-1', $records[0]['profile']['rut']);
        $this->assertSame('2021-06-16', $records[0]['profile']['birthdate']);
        $this->assertSame('Cristian Marcelo Aburto Vaez', $records[0]['profile']['guardian_name']);
        $this->assertTrue($records[0]['profile']['guardian_pickup_authorization']);
        $this->assertTrue($records[0]['profile']['is_pie_participant']);
        $this->assertSame('Trastorno del Lenguaje (TL)', $records[0]['profile']['pie_diagnosis']);
        $this->assertSame(2026, $records[0]['enrollment']['year']);
        $this->assertSame('Primer Nivel Transición A', $records[0]['enrollment']['course_name']);
        $this->assertSame('1', $records[0]['enrollment']['registration_number']);
    }

    public function test_it_rejects_an_unknown_pdf_layout(): void
    {
        $this->expectException(RuntimeException::class);

        (new LirmiStudentPdfParser)->parsePages(['Documento sin ficha de matrícula.']);
    }
}
