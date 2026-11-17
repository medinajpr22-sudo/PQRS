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
            <Head title="Acceso funcionarios" />

            <div className="mb-8 space-y-1">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Acceso funcionarios
                </h1>
                <p className="text-sm text-muted-foreground">
                    Ingresa al panel de PQRS de {tenant?.name}.
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
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Iniciar sesión
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
