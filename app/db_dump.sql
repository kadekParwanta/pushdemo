-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE users
(
  user_id serial NOT NULL,
  gcm_regid character varying(255) NOT NULL,
  username character varying(255) NOT NULL,
  salt character varying(23) NOT NULL,
  password character varying(88) NOT NULL,
  mail character varying(255) NOT NULL,
  role character varying(255) NOT NULL,
  created_at timestamp without time zone NOT NULL DEFAULT now(),
  CONSTRAINT users_pkey PRIMARY KEY (user_id)
)
WITH (
  OIDS=FALSE
);
ALTER TABLE gcm_users
  OWNER TO llkaqrivdelvht;

--
-- Dumping data for table `users`
--

INSERT INTO users (user_id, gcm_regid, username, salt, password, mail, role, created_at)
VALUES (1,'896351hjg15','ADMIN','1260889385528018eda0a12','YXylVBIE3HLEUNQEH5Z5bSua4vpEG0flg2V1OcpWw4wzel1nomjtkoG2XVKpug3R4hD18tI0Uj1r8z/3rXxtNg==', 'noreply@musicbox.nothing', 'ROLE_ADMIN', '2015-01-01 10:10:10');