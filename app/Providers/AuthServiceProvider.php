<?php

namespace App\Providers;

use App\Models\ApoyoProfesional\ApoyoAtencion;
use App\Models\ApoyoProfesional\ApoyoDerivacion;
use App\Models\ApoyoProfesional\ApoyoEntrevista;
use App\Models\ApoyoProfesional\ApoyoPlan;
use App\Models\ApoyoProfesional\ApoyoSeguimiento;
use App\Models\Infirmary\InfirmaryAccident;
use App\Models\Infirmary\InfirmaryAttention;
use App\Models\Infirmary\InfirmaryMedication;
use App\Models\Infirmary\InfirmaryMedicationAdministration;
use App\Models\Infirmary\InfirmaryMedicationAuthorization;
use App\Models\Infirmary\InfirmaryMedicationMovement;
use App\Models\It\ItEquipment;
use App\Models\It\ItEquipmentLoan;
use App\Models\It\ItEquipmentMaintenanceReport;
use App\Models\CentroApuntes\CentroApuntesAsignatura;
use App\Models\CentroApuntes\CentroApuntesMaquina;
use App\Models\CentroApuntes\CentroApuntesSolicitud;
use App\Models\CentroApuntes\PanolEntrega;
use App\Models\CentroApuntes\PanolInsumo;
use App\Models\CentroApuntes\PanolMovimiento;
use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\Convivencia\ConvivenciaInterview;
use App\Models\Convivencia\ConvivenciaMeasure;
use App\Models\Convivencia\ConvivenciaPlan as ConvivenciaPlanModel;
use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaSociogram;
use App\Models\Attendance\AttendanceGoal;
use App\Models\Attendance\AttendanceIntervention;
use App\Models\CalendarEvent;
use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaEspacio;
use App\Models\Library\BibliotecaObra;
use App\Models\Library\BibliotecaPlanLector;
use App\Models\Library\BibliotecaPrestamo;
use App\Models\Library\BibliotecaReserva;
use App\Models\Library\BibliotecaUsoEspacio;
use App\Models\Pme\PmeAction;
use App\Models\Pme\PmeActivity;
use App\Models\Pme\PmeAlert;
use App\Models\Pme\PmeCycle;
use App\Models\Pme\PmeDimension;
use App\Models\Pme\PmeEvidence;
use App\Models\Pme\PmeGeneratedReport;
use App\Models\Pme\PmeIndicator;
use App\Models\Pme\PmeIndicatorMeasurement;
use App\Models\Pme\PmeMilestone;
use App\Models\Pme\PmeObjective;
use App\Models\Pme\PmePlan;
use App\Models\Pme\PmeReflectiveMonitoring;
use App\Models\Pme\PmeSepIncome;
use App\Models\Pme\PmeStrategicGoalMeasurement;
use App\Models\Pme\PmeStrategy;
use App\Models\Pme\PmeStudentSepClassification;
use App\Models\PermissionRequest;
use App\Models\RiskPrevention\RiskPreventionAccident;
use App\Models\RiskPrevention\RiskPreventionDocument;
use App\Models\RiskPrevention\RiskPreventionEmergencyDrill;
use App\Models\RiskPrevention\RiskPreventionEmergencyPlan;
use App\Models\RiskPrevention\RiskPreventionEppDelivery;
use App\Models\RiskPrevention\RiskPreventionEppItem;
use App\Models\RiskPrevention\RiskPreventionFireExtinguisher;
use App\Models\RiskPrevention\RiskPreventionTraining;
use App\Models\Security\SecurityIncident;
use App\Models\Security\SecurityShift;
use App\Models\Task;
use App\Policies\CalendarEventPolicy;
use App\Policies\BibliotecaCatalogPolicy;
use App\Policies\BibliotecaLoanPolicy;
use App\Policies\BibliotecaPlanPolicy;
use App\Policies\BibliotecaReservationPolicy;
use App\Policies\BibliotecaSpacePolicy;
use App\Policies\ApoyoAtencionPolicy;
use App\Policies\ApoyoDerivacionPolicy;
use App\Policies\ApoyoEntrevistaPolicy;
use App\Policies\ApoyoPlanPolicy;
use App\Policies\ApoyoSeguimientoPolicy;
use App\Policies\CentroApuntesAsignaturaPolicy;
use App\Policies\CentroApuntesMaquinaPolicy;
use App\Policies\CentroApuntesSolicitudPolicy;
use App\Policies\ConvivenciaCasePolicy;
use App\Policies\ConvivenciaComplaintPolicy;
use App\Policies\ConvivenciaDailyLogPolicy;
use App\Policies\ConvivenciaDerivationPolicy;
use App\Policies\ConvivenciaInterviewPolicy;
use App\Policies\ConvivenciaMeasurePolicy;
use App\Policies\ConvivenciaPlanPolicy;
use App\Policies\ConvivenciaProtocolPolicy;
use App\Policies\ConvivenciaSociogramPolicy;
use App\Policies\AttendanceGoalPolicy;
use App\Policies\AttendanceInterventionPolicy;
use App\Policies\InfirmaryAccidentPolicy;
use App\Policies\InfirmaryAttentionPolicy;
use App\Policies\InfirmaryMedicationAuthorizationPolicy;
use App\Policies\InfirmaryMedicationPolicy;
use App\Policies\ItEquipmentLoanPolicy;
use App\Policies\ItEquipmentMaintenancePolicy;
use App\Policies\ItEquipmentPolicy;
use App\Policies\PanolEntregaPolicy;
use App\Policies\PanolInsumoPolicy;
use App\Policies\PanolMovimientoPolicy;
use App\Policies\PermissionRequestPolicy;
use App\Policies\PmePolicy;
use App\Policies\RiskPreventionPolicy;
use App\Policies\SecurityIncidentPolicy;
use App\Policies\SecurityShiftPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        AttendanceGoal::class => AttendanceGoalPolicy::class,
        AttendanceIntervention::class => AttendanceInterventionPolicy::class,
        CalendarEvent::class => CalendarEventPolicy::class,
        PermissionRequest::class => PermissionRequestPolicy::class,
        ApoyoAtencion::class => ApoyoAtencionPolicy::class,
        ApoyoDerivacion::class => ApoyoDerivacionPolicy::class,
        ApoyoSeguimiento::class => ApoyoSeguimientoPolicy::class,
        ApoyoPlan::class => ApoyoPlanPolicy::class,
        ApoyoEntrevista::class => ApoyoEntrevistaPolicy::class,
        ConvivenciaCase::class => ConvivenciaCasePolicy::class,
        ConvivenciaComplaint::class => ConvivenciaComplaintPolicy::class,
        ConvivenciaDerivation::class => ConvivenciaDerivationPolicy::class,
        ConvivenciaPlanModel::class => ConvivenciaPlanPolicy::class,
        ConvivenciaProtocol::class => ConvivenciaProtocolPolicy::class,
        ConvivenciaInterview::class => ConvivenciaInterviewPolicy::class,
        ConvivenciaMeasure::class => ConvivenciaMeasurePolicy::class,
        ConvivenciaDailyLog::class => ConvivenciaDailyLogPolicy::class,
        ConvivenciaSociogram::class => ConvivenciaSociogramPolicy::class,
        SecurityShift::class => SecurityShiftPolicy::class,
        SecurityIncident::class => SecurityIncidentPolicy::class,
        Task::class => TaskPolicy::class,
        RiskPreventionFireExtinguisher::class => RiskPreventionPolicy::class,
        RiskPreventionAccident::class => RiskPreventionPolicy::class,
        RiskPreventionEmergencyPlan::class => RiskPreventionPolicy::class,
        RiskPreventionEmergencyDrill::class => RiskPreventionPolicy::class,
        RiskPreventionEppItem::class => RiskPreventionPolicy::class,
        RiskPreventionEppDelivery::class => RiskPreventionPolicy::class,
        RiskPreventionTraining::class => RiskPreventionPolicy::class,
        RiskPreventionDocument::class => RiskPreventionPolicy::class,
        InfirmaryAttention::class => InfirmaryAttentionPolicy::class,
        InfirmaryMedication::class => InfirmaryMedicationPolicy::class,
        InfirmaryMedicationMovement::class => InfirmaryMedicationPolicy::class,
        InfirmaryMedicationAuthorization::class => InfirmaryMedicationAuthorizationPolicy::class,
        InfirmaryMedicationAdministration::class => InfirmaryMedicationAuthorizationPolicy::class,
        InfirmaryAccident::class => InfirmaryAccidentPolicy::class,
        ItEquipment::class => ItEquipmentPolicy::class,
        ItEquipmentLoan::class => ItEquipmentLoanPolicy::class,
        ItEquipmentMaintenanceReport::class => ItEquipmentMaintenancePolicy::class,
        BibliotecaObra::class => BibliotecaCatalogPolicy::class,
        BibliotecaEjemplar::class => BibliotecaCatalogPolicy::class,
        BibliotecaPrestamo::class => BibliotecaLoanPolicy::class,
        BibliotecaReserva::class => BibliotecaReservationPolicy::class,
        BibliotecaPlanLector::class => BibliotecaPlanPolicy::class,
        BibliotecaEspacio::class => BibliotecaSpacePolicy::class,
        BibliotecaUsoEspacio::class => BibliotecaSpacePolicy::class,
        CentroApuntesAsignatura::class => CentroApuntesAsignaturaPolicy::class,
        CentroApuntesMaquina::class => CentroApuntesMaquinaPolicy::class,
        CentroApuntesSolicitud::class => CentroApuntesSolicitudPolicy::class,
        PanolInsumo::class => PanolInsumoPolicy::class,
        PanolMovimiento::class => PanolMovimientoPolicy::class,
        PanolEntrega::class => PanolEntregaPolicy::class,
        PmePlan::class => PmePolicy::class,
        PmeCycle::class => PmePolicy::class,
        PmeDimension::class => PmePolicy::class,
        PmeObjective::class => PmePolicy::class,
        PmeStrategy::class => PmePolicy::class,
        PmeIndicator::class => PmePolicy::class,
        PmeIndicatorMeasurement::class => PmePolicy::class,
        PmeAction::class => PmePolicy::class,
        PmeActivity::class => PmePolicy::class,
        PmeMilestone::class => PmePolicy::class,
        PmeStrategicGoalMeasurement::class => PmePolicy::class,
        PmeReflectiveMonitoring::class => PmePolicy::class,
        PmeEvidence::class => PmePolicy::class,
        PmeSepIncome::class => PmePolicy::class,
        PmeStudentSepClassification::class => PmePolicy::class,
        PmeGeneratedReport::class => PmePolicy::class,
        PmeAlert::class => PmePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
