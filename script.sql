create table if not exists authors
(
    author_id     varchar(255) not null
        primary key,
    flags         varchar(255) null,
    verified      tinyint      null,
    public_flags  varchar(255) null,
    email         varchar(255) null,
    premium_type  varchar(255) null,
    username      varchar(255) null,
    locale        varchar(255) null,
    avatar        varchar(255) null,
    discriminator varchar(255) null,
    bot           tinyint      null,
    `system`      tinyint      null,
    mfa_enabled   tinyint      null
);

create table if not exists channels
(
    channel_id     varchar(255) not null
        primary key,
    owner_id       varchar(255) null,
    type           varchar(255) null,
    application_id varchar(255) null,
    parent_id      varchar(255) null,
    guild_id       varchar(255) null,
    nsfw           tinyint      null,
    is_private     tinyint      null,
    position       int          null,
    name           varchar(255) null
);

create table if not exists emoji_used
(
    guild_id    varchar(255) null,
    author_id   varchar(255) null,
    emoji_id    varchar(255) null,
    is_reaction tinyint      null,
    message_id  varchar(255) null,
    channel_id  varchar(255) null,
    id          int auto_increment
        primary key
);

create table if not exists emojis
(
    emoji_id varchar(255) not null
        primary key,
    name     varchar(255) null,
    guild_id varchar(255) null,
    animated tinyint      null
);

create table if not exists guilds
(
    guild_id          varchar(255) not null
        primary key,
    name              varchar(255) null,
    system_channel_id varchar(255) null,
    region            varchar(255) null,
    member_count      int          null,
    icon              varchar(255) null,
    joined_at         timestamp    null,
    owner_id          varchar(255) null
);

create table if not exists message_attachments
(
    attachment_id varchar(255) not null
        primary key,
    message_id    varchar(255) null,
    height        int          null,
    width         int          null,
    proxy_url     varchar(255) null,
    url           varchar(255) null,
    filename      varchar(255) null,
    content_type  varchar(255) null,
    size          int          null
);

create table if not exists messages
(
    message_id       varchar(255) not null
        primary key,
    content          text         null,
    guild_id         varchar(255) null,
    flags            varchar(255) null,
    edited_timestamp timestamp    null,
    channel_id       varchar(255) null,
    type             varchar(255) null,
    author_id        varchar(255) null,
    timestamp        timestamp    null
);


