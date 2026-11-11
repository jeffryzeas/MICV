<?php
namespace Tests\Feature;

use App\Http\Middleware\LocalDesktopAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class LocalDesktopAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (['MICV_LOCAL_TOKEN','MICV_LOCAL_USER','MICV_LOCAL_ROLE','MICV_LOCAL_PERMISSIONS','MICV_LOCAL_VALID_UNTIL'] as $name) {
            putenv($name);
        }
        parent::tearDown();
    }

    public function test_local_session_is_bound_to_the_authorized_account(): void
    {
        $user = User::create(['name'=>'Cliente','usuario'=>'local','email'=>'local@example.test','password'=>'secret12345','rol'=>'cliente','activo'=>true]);
        putenv('MICV_LOCAL_TOKEN='.str_repeat('x',48));
        putenv('MICV_LOCAL_USER='.$user->id);
        putenv('MICV_LOCAL_ROLE=cliente');
        putenv('MICV_LOCAL_VALID_UNTIL='.(time()+600));
        $request = Request::create('/inicio', 'GET', server:['REMOTE_ADDR'=>'127.0.0.1','HTTP_X_MICV_LOCAL'=>str_repeat('x',48)]);
        $response = (new LocalDesktopAccess)->handle($request, function($r) use ($user) {
            $this->assertSame($user->id, $r->user()->id);
            return response('ok');
        });
        $this->assertSame(200,$response->getStatusCode());
        $request->headers->remove('X-MiCV-Local');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new LocalDesktopAccess)->handle($request,fn()=>response('unreachable'));
    }

    public function test_normal_web_requests_are_unaffected(): void
    {
        $response=(new LocalDesktopAccess)->handle(Request::create('/'),fn()=>response('web'));
        $this->assertSame('web',$response->getContent());
    }

    public function test_local_permissions_also_limit_view_controls(): void
    {
        $user = User::create(['name'=>'Admin','usuario'=>'admin-local','email'=>'admin-local@example.test','password'=>'secret12345','rol'=>'admin','activo'=>true]);
        $this->assertTrue($user->tienePermiso('cartas'));
        request()->attributes->set('local_permissions', ['cv']);
        $this->assertFalse($user->tienePermiso('cartas'));
        $this->assertTrue($user->tienePermiso('cv'));
        request()->attributes->remove('local_permissions');
    }
}
