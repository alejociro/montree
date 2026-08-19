export type LocaleOption = {
    code: string;
    name: string;
    native: string;
};

export type TranslationReplacements = Record<string, string | number>;

export type Translator = (
    key: string,
    replacements?: TranslationReplacements,
) => string;

export type PluralTranslator = (
    key: string,
    count: number,
    replacements?: TranslationReplacements,
) => string;
