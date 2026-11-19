import { Form, Head, usePage } from '@inertiajs/react';
import AuthController from '@/actions/App/Http/Controllers/Tenant/Admin/AuthController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function TenantLogin() {
    const { tenant } = usePage().props;

    return (
        <>
            <Head title="Acceso del personal" />

            <div className="mt-6 mb-6">
                <h1 className="text-3xl font-bold tracking-tight">
                    Panel de PQRS
                </h1>
                <p className="mt-1 text-muted-foreground">
                    Inicia sesión para continuar en {tenant?.name}.
                </p>
            </div>

            <Form
                {...AuthController.store.form()}
                resetOnSuccess={['password']}
                className="grid gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo electrónico</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                autoComplete="email"
                            />
                            <InputError message={errors.email} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password">Contraseña</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="current-password"
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="flex items-center gap-3">
                            <Checkbox id="remember" name="remember" value="1" />
                            <Label htmlFor="remember">Recordarme</Label>
                        </div>
                        <Button
                            type="submit"
                            className="h-11 w-full rounded-xl text-base font-bold shadow-md"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Entrar
                        </Button>
                        <a
                            href="/"
                            className="text-center text-sm text-muted-foreground hover:text-foreground"
                        >
                            ← Volver al portal público
                        </a>
                    </>
                )}
            </Form>
        </>
    );
}
