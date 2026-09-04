<?php

namespace App\Http\Controllers;

use App\Models\SiteInstallation;
use App\Models\SiteInstallationImage;
use App\Models\StudentLifePost;
use App\Models\StudentLifePostImage;
use App\Models\Testimonial;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicSiteContentMediaController extends Controller
{
    public function testimonialImage(Testimonial $testimonial): StreamedResponse
    {
        abort_unless(
            Testimonial::query()->published()->whereKey($testimonial->getKey())->exists(),
            404,
        );

        return $this->imageResponse($testimonial->image_path, true);
    }

    public function studentLifeCover(StudentLifePost $studentLifePost): StreamedResponse
    {
        abort_unless(
            StudentLifePost::query()->published()->whereKey($studentLifePost->getKey())->exists(),
            404,
        );

        return $this->imageResponse($studentLifePost->cover_image_path, true);
    }

    public function studentLifeGallery(
        StudentLifePost $studentLifePost,
        StudentLifePostImage $studentLifePostImage,
    ): StreamedResponse {
        abort_unless(
            $studentLifePostImage->student_life_post_id === $studentLifePost->id
            && StudentLifePost::query()->published()->whereKey($studentLifePost->getKey())->exists(),
            404,
        );

        return $this->imageResponse($studentLifePostImage->image_path, true);
    }

    public function installationCover(SiteInstallation $siteInstallation): StreamedResponse
    {
        abort_unless(
            SiteInstallation::query()->published()->whereKey($siteInstallation->getKey())->exists(),
            404,
        );

        return $this->imageResponse($siteInstallation->cover_image_path, true);
    }

    public function installationGallery(
        SiteInstallation $siteInstallation,
        SiteInstallationImage $siteInstallationImage,
    ): StreamedResponse {
        abort_unless(
            $siteInstallationImage->site_installation_id === $siteInstallation->id
            && SiteInstallation::query()->published()->whereKey($siteInstallation->getKey())->exists(),
            404,
        );

        return $this->imageResponse($siteInstallationImage->image_path, true);
    }

    public function adminTestimonialImage(Testimonial $testimonial): StreamedResponse
    {
        return $this->imageResponse($testimonial->image_path, false);
    }

    public function adminStudentLifeCover(StudentLifePost $studentLifePost): StreamedResponse
    {
        return $this->imageResponse($studentLifePost->cover_image_path, false);
    }

    public function adminStudentLifeGallery(
        StudentLifePost $studentLifePost,
        StudentLifePostImage $studentLifePostImage,
    ): StreamedResponse {
        abort_unless($studentLifePostImage->student_life_post_id === $studentLifePost->id, 404);

        return $this->imageResponse($studentLifePostImage->image_path, false);
    }

    public function adminInstallationCover(SiteInstallation $siteInstallation): StreamedResponse
    {
        return $this->imageResponse($siteInstallation->cover_image_path, false);
    }

    public function adminInstallationGallery(
        SiteInstallation $siteInstallation,
        SiteInstallationImage $siteInstallationImage,
    ): StreamedResponse {
        abort_unless($siteInstallationImage->site_installation_id === $siteInstallation->id, 404);

        return $this->imageResponse($siteInstallationImage->image_path, false);
    }

    private function imageResponse(?string $path, bool $public): StreamedResponse
    {
        $disk = Storage::disk('public');
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => $public
                ? 'public, max-age=300, must-revalidate'
                : 'private, no-store, max-age=0',
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
