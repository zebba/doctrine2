<?php
/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ORM\Tools\Console\Command\SchemaTool;

use Symfony\Components\Console\Input\InputArgument,
    Symfony\Components\Console\Input\InputOption,
    Symfony\Components\Console;

/**
 * Command to create the database schema for a set of classes based on their mappings.
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @version $Revision$
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 */
class CreateCommand extends Console\Command\Command
{
    /**
     * @see Console\Command\Command
     */
    protected function configure()
    {
        $this
        ->setName('orm:schema-tool:create')
        ->setDescription(
            'Processes the schema and either create it directly on EntityManager Storage Connection or generate the SQL output.'
        )
        ->setDefinition(array(
            new InputArgument(
                'from-path', InputArgument::REQUIRED, 'The path of mapping information.'
            ),
            new InputOption(
                'from', null, InputOption::PARAMETER_REQUIRED | InputOption::PARAMETER_IS_ARRAY,
                'Optional paths of mapping information.',
                array()
            ),
            new InputOption(
                'dump-sql', null, InputOption::PARAMETER_NONE,
                'Instead of try to apply generated SQLs into EntityManager Storage Connection, output them.'
            )
        ))
        ->setHelp(<<<EOT
Processes the schema and either create it directly on EntityManager Storage Connection or generate the SQL output.
EOT
        );
    }

    /**
     * @see Console\Command\Command
     */
    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        $em = $this->getHelper('em')->getEntityManager();

        $reader = new ClassMetadataReader();
        $reader->setEntityManager($em);

        // Process source directories
        $fromPaths = array_merge(array($input->getArgument('from-path')), $input->getOption('from'));

        foreach ($fromPaths as $dirName) {
            $dirName = realpath($dirName);

            if ( ! file_exists($dirName)) {
                throw new \InvalidArgumentException(
                    sprintf("Mapping directory '<info>%s</info>' does not exist.", $dirName)
                );
            } else if ( ! is_readable($dirName)) {
                throw new \InvalidArgumentException(
                    sprintf("Mapping directory '<info>%s</info>' does not have read permissions.", $dirName)
                );
            }

            $reader->addMappingSource($dirName);
        }

        // Retrieving ClassMetadatas
        $metadatas = $reader->getMetadatas();

        if ( ! empty($metadatas)) {
            // Create SchemaTool
            $tool = new \Doctrine\ORM\Tools\SchemaTool($em);

            if ($input->getOption('dump-sql') === null) {
                $sqls = $tool->getCreateSchemaSql($metadatas);
                $output->write(implode(';' . PHP_EOL, $sqls));
            } else {
                $output->write('Creating database schema...' . PHP_EOL);
                $tool->createSchema($metadatas);
                $output->write('Database schema created successfully!' . PHP_EOL);
            }
        } else {
            $output->write('No Metadata Classes to process.' . PHP_EOL);
        }
    }
}