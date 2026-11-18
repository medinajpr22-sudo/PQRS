import { Form, Head, Link } from '@inertiajs/react';
import { Building2, Globe, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import TenantController from '@/actions/App/Http/Controllers/Central/TenantController';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/pqrs/page-header';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import tenants from '@/routes/tenants';

type Props = {
    plans: string[];
    baseDomain: string;
};

function slugify(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/\b(s\.?a\.?s?|ltda|e\.?s\.?p)\b\.?/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 30);
}

function prefixFrom(value: string): string {
    const words = value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .split(/\s+/)
        .filter((w) => w.length > 2);

    const prefix =
        words.length >= 2
            ? words
                  .slice(0, 3)
                  .map((w) => w[0])
                  .join('')
            : (words[0] ?? '').slice(0, 3);

    return prefix.toUpperCase().replace(/[^A-Z0-9]/g, '');
}

export default function CreateTenant({ plans, baseDomain }: Props) {
    const [name, setName] = useState('');
    const [subdomain, setSubdomain] = useState('');
    const [subdomainTouched, setSubdomainTouched] = useState(false);
    const [prefix, setPrefix] = useState('');
    const [prefixTouched, setPrefixTouched] = useState(false);
    const [database, setDatabase] = useState('');
    const [databaseTouched, setDatabaseTouched] = useState(false);
    const suggestDatabase = (slug: string) =>
        slug ? `tenant_${slug.replaceAll('-', '_')}` : '';

    return (
        <>
            <Head title="Nueva empresa" />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Nueva empresa"
                    description="Se creará su base de datos, su portal PQRS y un usuario administrador."
                />

                <Form {...TenantController.store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Building2 className="size-4" />
                                        Datos de la empresa
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="name">
                                            Razón social
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            value={name}
                                            onChange={(e) => {
                                                setName(e.target.value);

                                                if (!subdomainTouched) {
                                                    setSubdomain(
                                                        slugify(e.target.value),
                                                    );
                                                }

                                                if (
                                                    !subdomainTouched &&
                                                    !databaseTouched
                                                ) {
                                                    setDatabase(
                                                        suggestDatabase(
                                                            slugify(
                                                                e.target.value,
                                                            ),
                                                        ),
                                                    );
                                                }

                                                if (!prefixTouched) {
                                                    setPrefix(
                                                        prefixFrom(
                                                            e.target.value,
                                                        ),
                                                    );
                                                }
                                            }}
                                            placeholder="Ej: Aseguradora Andina S.A."
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="nit">NIT</Label>
                                        <Input
                                            id="nit"
                                            name="nit"
                                            placeholder="900.123.456-7"
                                        />
                                        <InputError message={errors.nit} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="plan">Plan</Label>
                                        <NativeSelect
                                            id="plan"
                                            name="plan"
                                            defaultValue={plans[0]}
                                        >
                                            {plans.map((p) => (
                                                <option
                                                    key={p}
                                                    value={p}
                                                    className="capitalize"
                                                >
                                                    {p.charAt(0).toUpperCase() +
                                                        p.slice(1)}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                        <InputError message={errors.plan} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">
                                            Correo de contacto
                                        </Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="phone">Teléfono</Label>
                                        <Input id="phone" name="phone" />
                                        <InputError message={errors.phone} />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Globe className="size-4" />
                                        Portal PQRS
                                    </CardTitle>
                                    <CardDescription>
                                        El subdominio también es el
                                        identificador interno de la empresa y no
                                        se puede cambiar.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="subdomain">
                                            Subdominio
                                        </Label>
                                        <div className="flex rounded-md shadow-xs">
                                            <Input
                                                id="subdomain"
                                                name="subdomain"
                                                required
                                                value={subdomain}
                                                onChange={(e) => {
                                                    setSubdomainTouched(true);
                                                    setSubdomain(
                                                        e.target.value.toLowerCase(),
                                                    );

                                                    if (!databaseTouched) {
                                                        setDatabase(
                                                            suggestDatabase(
                                                                e.target.value.toLowerCase(),
                                                            ),
                                                        );
                                                    }
                                                }}
                                                className="rounded-r-none font-mono shadow-none"
                                                placeholder="mi-empresa"
                                            />
                                            <span className="inline-flex items-center rounded-r-md border border-l-0 bg-muted px-3 font-mono text-sm text-muted-foreground">
                                                .{baseDomain}
                                            </span>
                                        </div>
                                        <InputError
                                            message={errors.subdomain}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="radicado_prefix">
                                            Prefijo de radicado
                                        </Label>
                                        <Input
                                            id="radicado_prefix"
                                            name="radicado_prefix"
                                            value={prefix}
                                            maxLength={6}
                                            onChange={(e) => {
                                                setPrefixTouched(true);
                                                setPrefix(
                                                    e.target.value.toUpperCase(),
                                                );
                                            }}
                                            className="font-mono uppercase"
                                            placeholder="ABC"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Ejemplo de radicado:{' '}
                                            <span className="font-mono">
                                                {prefix || 'ABC'}-
                                                {new Date().getFullYear()}-00001
                                            </span>
                                        </p>
                                        <InputError
                                            message={errors.radicado_prefix}
                                        />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="database">
                                            Nombre de la base de datos
                                        </Label>
                                        <Input
                                            id="database"
                                            name="database"
                                            required
                                            value={database}
                                            onChange={(e) => {
                                                setDatabaseTouched(true);
                                                setDatabase(
                                                    e.target.value.toLowerCase(),
                                                );
                                            }}
                                            className="font-mono"
                                            placeholder="tenant_mi_empresa"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Base de datos propia de la empresa.
                                            Solo minúsculas, números y guion
                                            bajo; no se puede cambiar después.
                                        </p>
                                        <InputError message={errors.database} />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <ShieldCheck className="size-4" />
                                        Administrador de la empresa
                                    </CardTitle>
                                    <CardDescription>
                                        Con esta cuenta la empresa ingresa a{' '}
                                        <span className="font-mono">
                                            {subdomain || 'mi-empresa'}.
                                            {baseDomain}/admin
                                        </span>{' '}
                                        para gestionar sus PQRS.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="admin_name">
                                            Nombre
                                        </Label>
                                        <Input
                                            id="admin_name"
                                            name="admin_name"
                                            required
                                        />
                                        <InputError
                                            message={errors.admin_name}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="admin_email">
                                            Correo
                                        </Label>
                                        <Input
                                            id="admin_email"
                                            name="admin_email"
                                            type="email"
                                            required
                                        />
                                        <InputError
                                            message={errors.admin_email}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="admin_password">
                                            Contraseña
                                        </Label>
                                        <PasswordInput
                                            id="admin_password"
                                            name="admin_password"
                                            required
                                            autoComplete="new-password"
                                        />
                                        <InputError
                                            message={errors.admin_password}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href={tenants.index()}>Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {processing
                                        ? 'Creando base de datos…'
                                        : 'Crear empresa'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CreateTenant.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Empresas', href: tenants.index() },
        { title: 'Nueva', href: tenants.create() },
    ],
};
