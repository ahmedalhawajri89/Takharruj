<?php

namespace Tests\Unit;

use App\Models\Student;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * الصورة الشخصية اختيارية، فلا يجوز أن يُسقط عرضُها صفحةً كاملة.
 *
 * على Vercel القرص للقراءة فقط ومجلد الصور لم يكن ضمن النشر: فتحُ القرص
 * يحاول إنشاء المجلد فيفشل، فكانت صفحة سجلّ التدقيق كلها تعطي 500 لأن
 * أحد الفاعلين فيها له صورة.
 */
class AvatarUrlTest extends TestCase
{
    public function test_an_unavailable_avatar_disk_falls_back_to_initials(): void
    {
        // مجلد لا يمكن إنشاؤه: أبوه ملف لا مجلد — كقرص للقراءة فقط
        $file = tempnam(sys_get_temp_dir(), 'avatar');
        config(['filesystems.disks.avatars.root' => $file . '/avatars']);
        Storage::forgetDisk('avatars');

        try {
            $student = (new Student())->forceFill(['name' => 'هبة بركة', 'avatar' => 'photo.jpg']);

            $this->assertNull($student->avatar_url, 'تعذّر التخزين، فالمتوقع الرجوع للأحرف الأولى لا رابط ولا استثناء.');
            $this->assertSame('هب', $student->initials);
        } finally {
            Storage::forgetDisk('avatars');
            @unlink($file);
        }
    }
}
